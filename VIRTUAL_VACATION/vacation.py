#!/usr/bin/env python3
"""PostfixAdmin Virtual Vacation modernization prototype.

This initial version provides configuration discovery, generation,
diagnostics, and a simple interactive SMTP test. It deliberately does not
process vacation mail yet and must not replace vacation.pl in Postfix
master.cf.

See VIRTUAL_VACATION/Contributions.txt for contributor credits.
"""

import argparse
import configparser
import importlib.util
import json
import os
import re
import shutil
import smtplib
import socket
import subprocess
import sys
from email.message import EmailMessage
from email.utils import formatdate, make_msgid, parseaddr
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional, Tuple

VERSION = "0.2.0"
MINIMUM_PYTHON = (3, 6)
DEFAULT_CONFIG_PATHS = (
    Path("/etc/mail/postfixadmin/vacation-python.conf"),
    Path("/etc/postfixadmin/vacation-python.conf"),
    Path.cwd() / "vacation-python.conf",
)
TABLE_KEYS = ("vacation", "vacation_notification", "alias", "alias_domain", "mailbox")
SAFE_SQL_IDENTIFIER = re.compile(r"^[A-Za-z_][A-Za-z0-9_$]*$")
PERL_ASSIGNMENT = re.compile(r"^\s*\$(?P<key>[A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?P<value>.*?)\s*;\s*(?:#.*)?$")


class CheckResult:
    def __init__(self, section: str, name: str, status: str, detail: str = "") -> None:
        self.section = section
        self.name = name
        self.status = status
        self.detail = detail


def add_result(results: List[CheckResult], section: str, name: str, status: str, detail: str = "") -> None:
    results.append(CheckResult(section, name, status, detail))


def discover_postfixadmin_roots(explicit: Optional[str] = None) -> List[Path]:
    candidates: List[Path] = []
    if explicit:
        candidates.append(Path(explicit))
    elif os.environ.get("POSTFIXADMIN_ROOT"):
        candidates.append(Path(os.environ["POSTFIXADMIN_ROOT"]))
    else:
        for base in (Path.cwd(), Path(__file__).resolve().parent):
            candidates.extend((base, *base.parents))

        candidates.extend(
            Path(path)
            for path in (
                "/var/www/html/postfixadmin",
                "/var/www/postfixadmin",
                "/usr/share/postfixadmin",
                "/opt/postfixadmin",
                "C:/xampp/htdocs/postfixadmin-master",
                "C:/xampp/htdocs/postfixadmin-v403",
            )
        )

        for web_root in (Path("/var/www"), Path("/srv/www")):
            if web_root.is_dir():
                candidates.extend(path.parent for path in web_root.glob("*/config.local.php"))
                candidates.extend(path.parent for path in web_root.glob("*/*/config.local.php"))

    found: List[Path] = []
    seen = set()
    for candidate in candidates:
        try:
            candidate = candidate.expanduser().resolve()
        except OSError:
            continue
        key = os.path.normcase(str(candidate))
        if key in seen:
            continue
        seen.add(key)
        if (candidate / "config.inc.php").is_file() and (candidate / "config.local.php").is_file():
            found.append(candidate)
    return found


def choose_postfixadmin_root(roots: List[Path], non_interactive: bool) -> Path:
    if not roots:
        raise RuntimeError("No PostfixAdmin installation with config.local.php was found")
    if len(roots) == 1:
        return roots[0]
    if non_interactive:
        rendered = ", ".join(str(path) for path in roots)
        raise RuntimeError(f"Multiple PostfixAdmin installations found: {rendered}; use --postfixadmin-root")

    print("PostfixAdmin installations found:")
    for index, root in enumerate(roots, start=1):
        print(f"  {index}. {root}")
    while True:
        selected = input("Select installation [1]: ").strip() or "1"
        if selected.isdigit() and 1 <= int(selected) <= len(roots):
            return roots[int(selected) - 1]
        print("Invalid selection.")


def find_php() -> Optional[str]:
    configured = os.environ.get("POSTFIXADMIN_PHP")
    if configured and Path(configured).is_file():
        return configured
    for candidate in (
        shutil.which("php"),
        "C:/xampp/php/windowsXamppPhp/php.exe",
        "C:/xampp/php/php.exe",
    ):
        if candidate and Path(candidate).is_file():
            return str(candidate)
    return None


def export_postfixadmin_config(root: Path, php_binary: Optional[str] = None) -> Dict[str, Any]:
    php_binary = php_binary or find_php()
    if not php_binary:
        raise RuntimeError("PHP CLI was not found; set POSTFIXADMIN_PHP or install php-cli")

    php_code = r'''
$root = realpath($argv[1]);
if ($root === false || !is_file($root . DIRECTORY_SEPARATOR . 'config.inc.php')) {
    fwrite(STDERR, "Invalid PostfixAdmin root\n");
    exit(2);
}
$CONF = array();
require $root . DIRECTORY_SEPARATOR . 'config.inc.php';
$keys = array(
    'database_type', 'database_host', 'database_port', 'database_user',
    'database_password', 'database_name', 'database_prefix', 'database_tables',
    'vacation_domain'
);
$result = array();
foreach ($keys as $key) {
    if (array_key_exists($key, $CONF)) {
        $result[$key] = $CONF[$key];
    }
}
$tableKeys = array('vacation', 'vacation_notification', 'alias', 'alias_domain', 'mailbox');
$prefix = isset($CONF['database_prefix']) ? (string) $CONF['database_prefix'] : '';
$tableMap = isset($CONF['database_tables']) && is_array($CONF['database_tables']) ? $CONF['database_tables'] : array();
$result['resolved_tables'] = array();
foreach ($tableKeys as $key) {
    $result['resolved_tables'][$key] = $prefix . (isset($tableMap[$key]) ? $tableMap[$key] : $key);
}
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
'''
    completed = subprocess.run(
        [php_binary, "-d", "display_errors=stderr", "-r", php_code, str(root)],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True,
        timeout=15,
        check=False,
    )
    if completed.returncode != 0:
        detail = completed.stderr.strip() or f"PHP exited with status {completed.returncode}"
        raise RuntimeError(f"Could not load PostfixAdmin configuration: {detail}")
    try:
        data = json.loads(completed.stdout)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"PostfixAdmin configuration exporter returned invalid JSON: {exc}") from exc
    if not isinstance(data, dict):
        raise RuntimeError("PostfixAdmin configuration exporter returned an unexpected value")
    return data


def perl_unquote(value: str) -> Any:
    value = value.strip()
    if len(value) >= 2 and value[0] == value[-1] == "'":
        return value[1:-1].replace("\\'", "'").replace("\\\\", "\\")
    if len(value) >= 2 and value[0] == value[-1] == '"':
        body = value[1:-1]
        return bytes(body, "utf-8").decode("unicode_escape")
    if re.fullmatch(r"-?[0-9]+", value):
        return int(value)
    if value.lower() in ("true", "yes"):
        return True
    if value.lower() in ("false", "no"):
        return False
    raise ValueError(f"unsupported Perl value: {value}")


def load_legacy_config(path: Path) -> Tuple[Dict[str, Any], List[str]]:
    values: Dict[str, Any] = {}
    warnings: List[str] = []
    for line_number, line in enumerate(path.read_text(encoding="utf-8").splitlines(), start=1):
        assignment = PERL_ASSIGNMENT.match(line)
        if not assignment:
            continue
        try:
            values[assignment.group("key")] = perl_unquote(assignment.group("value"))
        except ValueError as exc:
            warnings.append(f"line {line_number}: {exc}")
    return values, warnings


def is_legacy_config(path: Path) -> bool:
    if not path.is_file():
        return False
    try:
        for line in path.read_text(encoding="utf-8").splitlines():
            if PERL_ASSIGNMENT.match(line):
                return True
    except (OSError, UnicodeError):
        return False
    return False


def load_vacation_config(path: Path) -> Tuple[Dict[str, Any], List[str]]:
    parser = configparser.ConfigParser(interpolation=None)
    try:
        with path.open("r", encoding="utf-8") as handle:
            parser.read_file(handle)
    except configparser.Error as exc:
        raise RuntimeError("Invalid INI configuration {}: {}".format(path, exc))

    warnings: List[str] = []
    values: Dict[str, Any] = {}
    if parser.has_section("postfixadmin"):
        values["postfixadmin_root"] = parser.get("postfixadmin", "root", fallback="").strip()
    else:
        warnings.append("missing [postfixadmin] section")

    if parser.has_section("database"):
        database_keys = {
            "type": "database_type",
            "host": "database_host",
            "port": "database_port",
            "name": "database_name",
            "user": "database_user",
            "password": "database_password",
        }
        for ini_key, internal_key in database_keys.items():
            if parser.has_option("database", ini_key):
                values[internal_key] = parser.get("database", ini_key)

    if parser.has_section("vacation") and parser.has_option("vacation", "domain"):
        values["vacation_domain"] = parser.get("vacation", "domain")

    if parser.has_section("smtp"):
        values["smtp_server"] = parser.get("smtp", "server", fallback="localhost").strip()
        values["smtp_server_port"] = parser.getint("smtp", "port", fallback=25)
        values["smtp_helo"] = parser.get("smtp", "helo", fallback="").strip()
    else:
        warnings.append("missing [smtp] section; localhost:25 defaults will be used")
        values.update({"smtp_server": "localhost", "smtp_server_port": 25, "smtp_helo": ""})

    if parser.has_section("logging"):
        values["log_syslog"] = parser.getboolean("logging", "syslog", fallback=True)
        values["log_level"] = parser.get("logging", "level", fallback="info").strip().lower()
    return values, warnings


def find_vacation_config(explicit: Optional[str]) -> Optional[Path]:
    candidates = [Path(explicit)] if explicit else list(DEFAULT_CONFIG_PATHS)
    for candidate in candidates:
        if candidate.expanduser().is_file():
            return candidate.expanduser().resolve()
    return None


def normalize_db_type(value: Any) -> str:
    normalized = str(value or "").lower()
    if normalized in ("mysql", "mysqli", "mariadb"):
        return "mysql"
    if normalized in ("pgsql", "postgres", "postgresql", "pg"):
        return "postgresql"
    if normalized in ("sqlite", "sqlite3"):
        return "sqlite"
    return normalized


def module_available(module: str) -> bool:
    try:
        return importlib.util.find_spec(module) is not None
    except (ImportError, ModuleNotFoundError):
        return False


def select_database_driver(config: Dict[str, Any]) -> Optional[Tuple[str, str, str]]:
    db_type = normalize_db_type(config.get("db_type") or config.get("database_type"))
    version = sys.version_info[:2]

    if db_type == "mysql":
        if version >= (3, 8):
            package = "PyMySQL>=1.1.2"
        elif version >= (3, 7):
            package = "PyMySQL==1.1.1"
        else:
            package = "OS package: python3-pymysql or python3-PyMySQL"
        return ("pymysql", package, "MySQL/MariaDB database driver")

    if db_type == "postgresql":
        if version >= (3, 8) and module_available("psycopg"):
            return ("psycopg", "psycopg[binary]", "PostgreSQL database driver (Psycopg 3)")
        if module_available("psycopg2"):
            return ("psycopg2", "psycopg2-binary", "PostgreSQL database driver (Psycopg 2)")
        if version >= (3, 8):
            return ("psycopg", "psycopg[binary]", "PostgreSQL database driver (Psycopg 3)")
        if version >= (3, 7):
            return ("psycopg2", "psycopg2-binary==2.9.9", "PostgreSQL database driver (Psycopg 2)")
        return (
            "psycopg2",
            "OS package: python3-psycopg2",
            "PostgreSQL database driver (Psycopg 2)",
        )

    return None


def prompt_value(label: str, default: str, non_interactive: bool) -> str:
    if non_interactive:
        return default
    entered = input(f"{label} [{default}]: ").strip()
    return entered or default


def base_domain(hostname: str) -> str:
    labels = [label for label in hostname.strip().strip(".").lower().split(".") if label]
    if len(labels) >= 3:
        return ".".join(labels[1:])
    if labels:
        return ".".join(labels)
    return "localhost"


def default_test_sender(smtp_helo: str) -> str:
    return "noreply@{}".format(base_domain(smtp_helo))


def detected_fqdn() -> Optional[str]:
    hostname = socket.getfqdn().strip().strip(".")
    if not hostname or "." not in hostname:
        return None
    if hostname.lower() in ("localhost", "localhost.localdomain"):
        return None
    if any(character.isspace() for character in hostname):
        return None
    return hostname


def resolve_smtp_helo(config: Dict[str, Any]) -> str:
    configured = str(config.get("smtp_helo") or "").strip()
    if configured:
        helo = configured
    else:
        helo = detected_fqdn() or input("SMTP HELO: ").strip()
    if not helo or "." not in helo or any(character.isspace() for character in helo):
        raise RuntimeError("A valid SMTP HELO name is required for the test")
    return helo


def render_config(
    source_root: Path,
    smtp_server: str,
    smtp_port: int,
    smtp_helo: str,
) -> str:
    parser = configparser.ConfigParser(interpolation=None)
    parser["postfixadmin"] = {
        "root": source_root.as_posix(),
    }
    parser["smtp"] = {
        "server": smtp_server,
        "port": str(smtp_port),
        "helo": smtp_helo,
    }
    parser["logging"] = {
        "syslog": "true",
        "level": "info",
    }
    from io import StringIO

    output = StringIO()
    output.write("# PostfixAdmin Virtual Vacation Python configuration\n")
    output.write("# Database settings and vacation_domain are inherited from PostfixAdmin.\n\n")
    parser.write(output)
    return output.getvalue()


def init_config(args: argparse.Namespace) -> int:
    roots = discover_postfixadmin_roots(args.postfixadmin_root)
    root = choose_postfixadmin_root(roots, args.non_interactive)
    source = export_postfixadmin_config(root, args.php)

    print(f"PostfixAdmin configuration: {root / 'config.local.php'}")
    print(f"Database: {normalize_db_type(source.get('database_type'))} / {source.get('database_name', '')}")
    print(f"Database host: {source.get('database_host') or 'local socket/default'}")
    print(f"Database user: {source.get('database_user', '')}")
    print("Database password: " + ("configured (hidden)" if source.get("database_password") else "empty"))
    print(f"Vacation domain: {source.get('vacation_domain', '')}")

    legacy: Dict[str, Any] = {}
    if args.import_legacy:
        legacy_path = Path(args.import_legacy).expanduser().resolve()
        if not legacy_path.is_file():
            raise RuntimeError("Legacy configuration not found: {}".format(legacy_path))
        legacy, warnings = load_legacy_config(legacy_path)
        print("Legacy configuration: {}".format(legacy_path))
        for warning in warnings:
            print("WARNING: {}".format(warning), file=sys.stderr)

    smtp_server = prompt_value("SMTP server", str(legacy.get("smtp_server", "localhost")), args.non_interactive)
    smtp_port = int(prompt_value("SMTP port", str(legacy.get("smtp_server_port", 25)), args.non_interactive))
    smtp_helo = prompt_value("SMTP HELO", str(legacy.get("smtp_helo", socket.getfqdn())), args.non_interactive)
    destination = Path(args.config or "/etc/postfixadmin/vacation-python.conf").expanduser()
    if args.import_legacy and destination.resolve() == legacy_path:
        raise RuntimeError("The Python configuration destination cannot be the legacy Perl configuration")
    if destination.exists() and is_legacy_config(destination):
        raise RuntimeError(
            "Refusing to overwrite a Perl vacation.conf; use a separate vacation-python.conf path"
        )
    if destination.exists() and not args.force:
        raise RuntimeError(f"Configuration already exists: {destination}; use --force to replace it")

    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_text(render_config(root, smtp_server, smtp_port, smtp_helo), encoding="utf-8")
    try:
        destination.chmod(0o640)
    except OSError as exc:
        print(f"WARNING: could not set mode 0640 on {destination}: {exc}", file=sys.stderr)
    print(f"Created: {destination}")
    print("Review the file owner and group for the service user configured in Postfix master.cf.")
    print("Next: run vacation.py --check --config " + str(destination))
    return 0


def required_modules(config: Dict[str, Any]) -> List[Tuple[str, str, str]]:
    modules: List[Tuple[str, str, str]] = []
    database_driver = select_database_driver(config)
    if database_driver:
        modules.append(database_driver)
    if not config.get("smtp_server", "localhost"):
        modules.append(("dns.resolver", "dnspython", "direct MX delivery"))
    return modules


def check_dependencies(config: Dict[str, Any], results: List[CheckResult]) -> bool:
    ok = True
    version = sys.version_info[:2]
    if version < MINIMUM_PYTHON:
        add_result(
            results,
            "Python",
            "Version",
            "FAILED",
            "{}; Python {}.{} or newer is required".format(sys.version.split()[0], *MINIMUM_PYTHON),
        )
        ok = False
    elif version < (3, 8):
        add_result(
            results,
            "Python",
            "Version",
            "WARNING",
            "{}; supported, but an OS-supported newer Python is preferable".format(sys.version.split()[0]),
        )
    else:
        add_result(results, "Python", "Version", "OK", sys.version.split()[0])
    db_type = normalize_db_type(config.get("db_type") or config.get("database_type"))
    if not db_type:
        add_result(
            results,
            "Dependencies",
            "Database driver",
            "NOT TESTED",
            "database type is unavailable because PostfixAdmin configuration was not loaded",
        )
        return False

    modules = required_modules(config)
    if not modules:
        add_result(results, "Dependencies", "External packages", "OK", "none required for selected features")
    for module, package, reason in modules:
        available = module_available(module)
        if not available:
            if package.startswith("OS package:"):
                detail = "required for {}; install {}".format(reason, package)
            else:
                detail = "required for {}; prefer the OS package, or install: python3 -m pip install '{}'".format(reason, package)
            add_result(results, "Dependencies", package, "MISSING", detail)
            ok = False
        else:
            add_result(results, "Dependencies", module, "OK", "{}; module import available".format(reason))
    return ok


def connect_database(config: Dict[str, Any]):
    db_type = normalize_db_type(config.get("db_type") or config.get("database_type"))
    host = str(config.get("db_host") or config.get("database_host") or "")
    name = str(config.get("db_name") or config.get("database_name") or "")
    user = str(config.get("db_username") or config.get("database_user") or "")
    password = str(config.get("db_password") or config.get("database_password") or "")
    port_value = config.get("database_port") or config.get("db_port") or ""

    if db_type == "mysql":
        import pymysql

        kwargs: Dict[str, Any] = {"host": host or "localhost", "user": user, "password": password, "database": name, "connect_timeout": 5}
        if port_value:
            kwargs["port"] = int(port_value)
        return pymysql.connect(**kwargs)
    if db_type == "postgresql":
        driver = select_database_driver(config)
        if driver is None:
            raise RuntimeError("No PostgreSQL driver could be selected")
        adapter = importlib.import_module(driver[0])
        kwargs = {"dbname": name, "user": user, "password": password, "connect_timeout": 5}
        if host:
            kwargs["host"] = host
        if port_value:
            kwargs["port"] = int(port_value)
        return adapter.connect(**kwargs)
    if db_type == "sqlite":
        import sqlite3

        return sqlite3.connect(name, timeout=5)
    raise RuntimeError(f"Unsupported database type: {db_type or '(empty)'}")


def resolve_tables(config: Dict[str, Any], postfixadmin: Optional[Dict[str, Any]]) -> Dict[str, str]:
    if postfixadmin and isinstance(postfixadmin.get("resolved_tables"), dict):
        return {key: str(postfixadmin["resolved_tables"].get(key, key)) for key in TABLE_KEYS}
    return {key: key for key in TABLE_KEYS}


def check_database(config: Dict[str, Any], postfixadmin: Optional[Dict[str, Any]], dependencies_ok: bool, results: List[CheckResult]) -> bool:
    if not dependencies_ok:
        add_result(results, "Database", "Connection", "NOT TESTED", "a required database driver is missing")
        return False
    connection = None
    try:
        connection = connect_database(config)
        add_result(results, "Database", "Connection", "OK")
        tables = resolve_tables(config, postfixadmin)
        cursor = connection.cursor()
        all_tables_ok = True
        for key, table in tables.items():
            if not SAFE_SQL_IDENTIFIER.fullmatch(table):
                add_result(results, "Database", f"Table {key}", "FAILED", f"unsafe or unsupported identifier: {table}")
                all_tables_ok = False
                continue
            try:
                cursor.execute(f"SELECT 1 FROM {table} LIMIT 1")
                add_result(results, "Database", f"Table {key}", "OK", table)
            except Exception as exc:  # Drivers expose different exception hierarchies.
                add_result(results, "Database", f"Table {key}", "FAILED", str(exc))
                all_tables_ok = False
                try:
                    connection.rollback()
                except Exception:
                    pass
        return all_tables_ok
    except Exception as exc:
        add_result(results, "Database", "Connection", "FAILED", str(exc))
        return False
    finally:
        if connection is not None:
            connection.close()


def check_smtp(config: Dict[str, Any], results: List[CheckResult]) -> bool:
    server = str(config.get("smtp_server", "localhost"))
    if not server:
        add_result(results, "Delivery", "SMTP", "NOT TESTED", "direct MX delivery selected")
        return True
    port = int(config.get("smtp_server_port", 25))
    try:
        with smtplib.SMTP(server, port, timeout=5) as client:
            code, response = client.noop()
        if 200 <= code < 400:
            add_result(results, "Delivery", "SMTP", "OK", f"{server}:{port}")
            return True
        add_result(results, "Delivery", "SMTP", "FAILED", f"NOOP returned {code}: {response!r}")
        return False
    except Exception as exc:
        add_result(results, "Delivery", "SMTP", "FAILED", f"{server}:{port}: {exc}")
        return False


def valid_email_address(address: str) -> bool:
    if not address or "\r" in address or "\n" in address:
        return False
    parsed = parseaddr(address)[1]
    return parsed == address and "@" in parsed and not parsed.startswith("@") and not parsed.endswith("@")


def run_test(args: argparse.Namespace) -> int:
    config_path = find_vacation_config(args.config)
    if not config_path:
        raise RuntimeError("No vacation-python.conf found; run --init-config first or use --config")

    config, warnings = load_vacation_config(config_path)
    for warning in warnings:
        print("WARNING: {}".format(warning), file=sys.stderr)

    server = str(config.get("smtp_server") or "localhost")
    port_text = str(config.get("smtp_server_port", 25))
    try:
        port = int(port_text)
    except ValueError:
        raise RuntimeError("SMTP port must be a number")
    if port < 1 or port > 65535:
        raise RuntimeError("SMTP port must be between 1 and 65535")
    helo = resolve_smtp_helo(config)
    sender = prompt_value("MAIL FROM", default_test_sender(helo), False)
    if not valid_email_address(sender):
        raise RuntimeError("Invalid test sender address")

    recipient = input("RCPT TO: ").strip()
    if not valid_email_address(recipient):
        raise RuntimeError("Invalid test recipient address")

    message = EmailMessage()
    message["From"] = sender
    message["To"] = recipient
    message["Subject"] = "PostfixAdmin vacation.py test"
    message["Date"] = formatdate(localtime=True)
    message["Message-ID"] = make_msgid(domain=helo)
    message["Auto-Submitted"] = "auto-generated"
    message["X-PostfixAdmin-Vacation-Test"] = "yes"
    message.set_content(
        "This is a test message sent by PostfixAdmin vacation.py.\n\n"
        "SMTP server: {}:{}\n"
        "SMTP HELO: {}\n".format(server, port, helo)
    )

    print("Sending test message from {} to {} using {}:{}...".format(sender, recipient, server, port))
    try:
        with smtplib.SMTP(server, port, local_hostname=helo, timeout=10) as client:
            refused = client.send_message(message, from_addr=sender, to_addrs=[recipient])
    except Exception as exc:
        raise RuntimeError("SMTP test failed: {}".format(exc))

    if refused:
        raise RuntimeError("SMTP server refused the test recipient: {}".format(refused))
    print("Test message sent successfully.")
    return 0


def print_results(results: Iterable[CheckResult]) -> None:
    current_section = None
    for result in results:
        if result.section != current_section:
            if current_section is not None:
                print()
            print(result.section + ":")
            current_section = result.section
        suffix = " - {}".format(result.detail) if result.detail else ""
        print(f"  {result.name:<28} {result.status}{suffix}")


def run_check(args: argparse.Namespace, dependencies_only: bool = False) -> int:
    results: List[CheckResult] = []
    config_path = find_vacation_config(args.config)
    config: Dict[str, Any] = {}
    postfixadmin: Optional[Dict[str, Any]] = None

    if config_path:
        config, warnings = load_vacation_config(config_path)
        add_result(results, "Configuration", "Vacation config", "OK", str(config_path))
        for warning in warnings:
            add_result(results, "Configuration", "Parse warning", "WARNING", warning)
    elif not dependencies_only:
        add_result(results, "Configuration", "Vacation config", "MISSING", "use --config or run --init-config")

    root_arg = args.postfixadmin_root or config.get("postfixadmin_root")
    roots = discover_postfixadmin_roots(root_arg)
    if roots:
        try:
            root = choose_postfixadmin_root(roots, True)
            postfixadmin = export_postfixadmin_config(root, args.php)
            add_result(results, "Configuration", "PostfixAdmin config", "OK", str(root / "config.local.php"))
            # Values explicitly present in vacation-python.conf remain overrides.
            inherited = {
                "database_type": postfixadmin.get("database_type"),
                "database_host": postfixadmin.get("database_host"),
                "database_port": postfixadmin.get("database_port"),
                "database_user": postfixadmin.get("database_user"),
                "database_password": postfixadmin.get("database_password"),
                "database_name": postfixadmin.get("database_name"),
                "vacation_domain": postfixadmin.get("vacation_domain"),
            }
            config = {**inherited, **config}
        except Exception as exc:
            add_result(results, "Configuration", "PostfixAdmin config", "FAILED", str(exc))
    elif root_arg:
        add_result(results, "Configuration", "PostfixAdmin config", "MISSING", str(root_arg))
    else:
        add_result(
            results,
            "Configuration",
            "PostfixAdmin config",
            "MISSING",
            "no installation found; use --postfixadmin-root /path/to/postfixadmin",
        )

    dependencies_ok = check_dependencies(config, results)
    if not dependencies_only and config_path:
        check_database(config, postfixadmin, dependencies_ok, results)
        check_smtp(config, results)

    print("PostfixAdmin Virtual Vacation - system check\n")
    print_results(results)
    failed = any(result.status in ("FAILED", "MISSING") for result in results)
    print("\nResult: " + ("FAILED" if failed else "OK"))
    return 1 if failed else 0


def show_config_path(args: argparse.Namespace) -> int:
    path = find_vacation_config(args.config)
    if path:
        print(path)
        return 0
    print("No vacation-python.conf found", file=sys.stderr)
    return 1


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    action = parser.add_mutually_exclusive_group()
    action.add_argument("--check", action="store_true", help="check configuration, dependencies, database, and SMTP")
    action.add_argument("--check-dependencies", action="store_true", help="report all required Python packages")
    action.add_argument("--init-config", action="store_true", help="create vacation-python.conf from PostfixAdmin configuration")
    action.add_argument("--test", action="store_true", help="interactively send a simple SMTP test message")
    action.add_argument("--show-config-path", action="store_true", help="print the selected vacation-python.conf path")
    parser.add_argument("--config", help="vacation-python.conf path")
    parser.add_argument("--import-legacy", help="read supported defaults from an existing Perl vacation.conf")
    parser.add_argument("--postfixadmin-root", help="directory containing config.inc.php and config.local.php")
    parser.add_argument("--php", help="PHP CLI binary")
    parser.add_argument("--non-interactive", action="store_true", help="use defaults and fail on ambiguity")
    parser.add_argument("--force", action="store_true", help="replace an existing generated configuration")
    parser.add_argument("--version", action="version", version=f"%(prog)s {VERSION}")
    parser.add_argument("-f", dest="envelope_sender", help=argparse.SUPPRESS)
    parser.add_argument("recipient", nargs="?", help=argparse.SUPPRESS)
    return parser


def main(argv: Optional[List[str]] = None) -> int:
    args = build_parser().parse_args(argv)
    try:
        if args.init_config:
            return init_config(args)
        if args.check_dependencies:
            return run_check(args, dependencies_only=True)
        if args.test:
            return run_test(args)
        if args.show_config_path:
            return show_config_path(args)
        if args.check:
            return run_check(args)
        print(
            "This initial version provides setup and diagnostics only; it does not send vacation replies. "
            "Use --init-config, --check, or --test; do not install it in Postfix master.cf yet.",
            file=sys.stderr,
        )
        return 69
    except (OSError, RuntimeError, subprocess.SubprocessError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
