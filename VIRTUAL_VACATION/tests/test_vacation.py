import importlib.util
import io
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

MODULE_PATH = Path(__file__).resolve().parents[1] / "vacation.py"
SPEC = importlib.util.spec_from_file_location("vacation", str(MODULE_PATH))
vacation = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
sys.modules[SPEC.name] = vacation
SPEC.loader.exec_module(vacation)


class VacationConfigTest(unittest.TestCase):
    def test_no_action_prints_one_status_line(self):
        stderr = io.StringIO()
        with mock.patch("sys.stderr", stderr):
            self.assertEqual(69, vacation.main([]))
        self.assertEqual(1, len(stderr.getvalue().splitlines()))

    def test_generated_ini_inherits_postfixadmin_configuration(self):
        rendered = vacation.render_config(
            Path("/var/www/html/postfixadmin"),
            "localhost",
            25,
            "mail.example.org",
        )

        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "vacation-python.conf"
            path.write_text(rendered, encoding="utf-8")
            values, warnings = vacation.load_vacation_config(path)

        self.assertEqual([], warnings)
        self.assertEqual("/var/www/html/postfixadmin", values["postfixadmin_root"])
        self.assertEqual("localhost", values["smtp_server"])
        self.assertEqual(25, values["smtp_server_port"])
        self.assertEqual("mail.example.org", values["smtp_helo"])
        self.assertNotIn("test_sender", values)
        self.assertNotIn("database_password", values)

    def test_legacy_import_reads_simple_values_without_execution(self):
        content = """\
$db_password = 'secret';
$smtp_server = 'localhost';
$smtp_server_port = 25;
$smtp_helo = 'triton.example.org';
system('must-not-run');
1;
"""
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "vacation.conf"
            path.write_text(content, encoding="utf-8")
            values, warnings = vacation.load_legacy_config(path)
            self.assertTrue(vacation.is_legacy_config(path))

        self.assertEqual([], warnings)
        self.assertEqual("localhost", values["smtp_server"])
        self.assertEqual(25, values["smtp_server_port"])
        self.assertEqual("triton.example.org", values["smtp_helo"])

    def test_generated_ini_is_not_detected_as_legacy(self):
        rendered = vacation.render_config(
            Path("/var/www/html/postfixadmin"), "localhost", 25, "mail.example.org"
        )
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "vacation-python.conf"
            path.write_text(rendered, encoding="utf-8")
            self.assertFalse(vacation.is_legacy_config(path))

    def test_email_address_validation_rejects_header_injection(self):
        self.assertTrue(vacation.valid_email_address("admin@example.org"))
        self.assertFalse(vacation.valid_email_address("admin@example.org\nBcc: victim@example.org"))
        self.assertFalse(vacation.valid_email_address("not-an-address"))

    def test_interactive_test_sends_predefined_message(self):
        rendered = vacation.render_config(
            Path("/var/www/html/postfixadmin"), "localhost", 25, "mail.example.org"
        )
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "vacation-python.conf"
            path.write_text(rendered, encoding="utf-8")
            args = vacation.argparse.Namespace(config=str(path))
            smtp = mock.MagicMock()
            smtp.__enter__.return_value.send_message.return_value = {}
            answers = ["", "admin@example.org"]
            with mock.patch("builtins.input", side_effect=answers):
                with mock.patch.object(vacation.smtplib, "SMTP", return_value=smtp):
                    self.assertEqual(0, vacation.run_test(args))

        sent_message = smtp.__enter__.return_value.send_message.call_args[0][0]
        self.assertEqual("noreply@example.org", sent_message["From"])
        self.assertEqual("admin@example.org", sent_message["To"])
        self.assertEqual("PostfixAdmin vacation.py test", sent_message["Subject"])

    def test_base_domain_is_derived_from_machine_hostname(self):
        self.assertEqual("example.org", vacation.base_domain("mail.example.org"))
        self.assertEqual("noreply@example.org", vacation.default_test_sender("mail.example.org"))

    def test_smtp_helo_uses_configuration_then_detection_then_prompt(self):
        with mock.patch("builtins.input") as prompt:
            self.assertEqual("configured.example.org", vacation.resolve_smtp_helo({"smtp_helo": "configured.example.org"}))
            prompt.assert_not_called()

        with mock.patch.object(vacation.socket, "getfqdn", return_value="detected.example.org"):
            with mock.patch("builtins.input") as prompt:
                self.assertEqual("detected.example.org", vacation.resolve_smtp_helo({}))
                prompt.assert_not_called()

        with mock.patch.object(vacation.socket, "getfqdn", return_value="localhost"):
            with mock.patch("builtins.input", return_value="entered.example.org"):
                self.assertEqual("entered.example.org", vacation.resolve_smtp_helo({}))

    def test_dependencies_follow_selected_database(self):
        mysql = vacation.required_modules({"database_type": "mysql", "smtp_server": "localhost"})
        postgres = vacation.required_modules({"database_type": "pgsql", "smtp_server": "localhost"})
        direct_mx = vacation.required_modules({"database_type": "mysql", "smtp_server": ""})

        self.assertEqual(["pymysql"], [item[0] for item in mysql])
        self.assertIn(postgres[0][0], ("psycopg", "psycopg2"))
        self.assertEqual(["pymysql", "dns.resolver"], [item[0] for item in direct_mx])

    def test_mysql_requirement_follows_python_version(self):
        config = {"database_type": "mysql"}
        with mock.patch.object(vacation.sys, "version_info", (3, 6, 15)):
            self.assertTrue(vacation.select_database_driver(config)[1].startswith("OS package:"))
        with mock.patch.object(vacation.sys, "version_info", (3, 7, 17)):
            self.assertEqual("PyMySQL==1.1.1", vacation.select_database_driver(config)[1])
        with mock.patch.object(vacation.sys, "version_info", (3, 8, 20)):
            self.assertEqual("PyMySQL>=1.1.2", vacation.select_database_driver(config)[1])

    def test_postgresql_prefers_psycopg3_and_falls_back_to_psycopg2(self):
        config = {"database_type": "pgsql"}
        with mock.patch.object(vacation.sys, "version_info", (3, 11, 0)):
            with mock.patch.object(vacation, "module_available", side_effect=lambda name: name == "psycopg"):
                self.assertEqual("psycopg", vacation.select_database_driver(config)[0])
            with mock.patch.object(vacation, "module_available", side_effect=lambda name: name == "psycopg2"):
                self.assertEqual("psycopg2", vacation.select_database_driver(config)[0])

    def test_dependencies_cannot_pass_without_database_type(self):
        results = []
        self.assertFalse(vacation.check_dependencies({}, results))
        self.assertEqual("NOT TESTED", results[-1].status)
        self.assertEqual("Database driver", results[-1].name)


if __name__ == "__main__":
    unittest.main()
