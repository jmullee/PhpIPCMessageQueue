#!/bin/bash

: > /tmp/logfile.log
chmod 0666 /tmp/logfile.log
tail -f /tmp/logfile.log &
TAILPID=$!

php -f ./examples/rand/example_q_listener_daemon.php &
sleep 1
php -f ./examples/rand/example_q_listener_msgsource.php
sleep 1
php -f ./examples/rand/example_q_listener_del_queue.php
sleep 1
kill -HUP $TAILPID

