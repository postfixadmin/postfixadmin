<?php

class Exec
{
    public readonly string $stdout;
    public readonly string $stderr;
    public readonly int $retval;

    /**
     *  Run an external command via proc_open.
     *
     *  Used for post-creation, post-deletion, post-password-change scripts etc.
     *  Handles process creation, optional stdin data, stdout capture, and error logging.
     *
     *  $command can be either:
     *  - string: full shell command (callers should escapeshellarg() arguments and append 2>&1)
     *  - array: [script, arg1, arg2, ...] — PHP handles escaping, no shell involved (preferred)
     *
     * @param string|array $command shell command string or array of [script, arg1, arg2, ...]
     * @param string|null $stdin_data optional data to write to the process stdin
     * /
     */
    public function __construct(string|array $command, ?string $stdin_data = null)
    {
        $spec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"], // stderr
        ];

        $proc = proc_open($command, $spec, $pipes);

        $cmd_display = is_array($command) ? implode(' ', $command) : $command;

        if (!$proc) {
            error_log("postfixadmin: can't proc_open: $cmd_display");
            throw new \RuntimeException("can't proc_open: $cmd_display");
        }

        if ($stdin_data !== null) {
            fwrite($pipes[0], $stdin_data);
        }
        fclose($pipes[0]);

        $this->stdout = stream_get_contents($pipes[1]);
        $this->stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->retval = proc_close($proc);
    }

    /**
     * @see __construct
     */
    public static function run(string|array $command, ?string $stdin_data = null): self
    {
        return new self($command, $stdin_data);
    }
}
