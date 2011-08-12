<?php
/*
	Copyright 2011 John Mullee

	This file is part of PhpIPCMessageQueue.

	PhpIPCMessageQueue is free software: you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation, either version 3 of
	the License, or (at your option) any later version.

	PhpIPCMessageQueue is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
	without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
	See the GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with PhpIPCMessageQueue.
	If not, see http://www.gnu.org/licenses/.
*/

define('MSGQ_KEY',3054);
define('MSGQ_TYPE',9);
define('MSGQ_UID',0);
define('MSGQ_GID',33);
define('MSGQ_MODE','0666');

require_once('ipc_message_queue.php');

class sys_q_listener
	extends ipc_message_queue
	{
	public function __construct()
		{
		parent::__construct(MSGQ_KEY, MSGQ_TYPE, MSGQ_UID, MSGQ_GID, MSGQ_MODE);
		}

	protected function msg_received($type,$msg)
		{
		logmsg(WARN,'Example Msg received: msg = '.$msg,__FILE__,__LINE__);
		// take a random amount of time..
		sleep(rand(1,10));
		}
	}

