<?php
require_once('example_q_listener.php');
logmsg(WARN,'Emitting Message',__FILE__,__LINE__);
$q = new example_q_listener();
$q->send_message(rand());

