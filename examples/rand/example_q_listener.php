<?php

define('MSGQ_KEY',3053);
define('MSGQ_TYPE',7);
define('MSGQ_UID',0);
define('MSGQ_GID',33);
define('MSGQ_MODE','0666');

require_once('ipc_message_queue.php');

class example_q_listener
	extends ipc_message_queue
	{
	public function __construct()
		{
		parent::__construct(MSGQ_KEY, MSGQ_TYPE, MSGQ_UID, MSGQ_GID, MSGQ_MODE);
		}

	protected function msg_received($type,$msg)
		{
		logmsg(WARN,'Example Msg received: msg = '.$msg,__FILE__,__LINE__);
		}
	}

