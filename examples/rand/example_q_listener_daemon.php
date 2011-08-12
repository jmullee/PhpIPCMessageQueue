<?php
require_once('example_q_listener.php');
logmsg(WARN,'Starting Q Listener',__FILE__,__LINE__);
$q = new example_q_listener();
$q->process_messages();

