require ["vnd.dovecot.pipe", "copy", "imapsieve"];

# Called when a user moves a message into the Junk mailbox.
# The helper script must be available under sieve_pipe_bin_dir.
pipe :copy "rspamd-learn-spam.sh";
