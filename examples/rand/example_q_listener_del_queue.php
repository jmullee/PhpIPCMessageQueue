<?php
require_once('example_q_listener.php');
logmsg(WARN,'Deleting Queue',__FILE__,__LINE__);
$q = new example_q_listener();
$q->deletequeue();


