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


	see: http://stackoverflow.com/questions/909791/asynchronous-processing-or-message-queues-in-php-cakephp
	and: http://nubyonrails.com/articles/about-this-blog-beanstalk-messaging-queue
	and: http://beej.us/guide/bgipc/output/html/singlepage/bgipc.html#mq

*/

/*
msgmax : Maximum size of a message
msgmnb : Maximum message queue size

4k messages of size 16b
	echo "16" > /proc/sys/kernel/msgmax
	echo "65535" > /proc/sys/kernel/msgmnb
63 messages of size 1024b
	echo "1024" > /proc/sys/kernel/msgmax
	echo "65535" > /proc/sys/kernel/msgmnb
*/
error_reporting(E_ALL | E_STRICT);

require_once('../PhpUnixDaemon/daemon.php');
require_once('errno.php');

// msg_receive() params
if(!defined('MSG_SIZE_MAX'))        define('MSG_SIZE_MAX',1024);
if(!defined('MSG_Q_ALWAYS_CREATE')) define('MSG_Q_ALWAYS_CREATE',true); // if not exists : fail or create

define('SERIALISE',0);
define('MSG_TYPE_ANY',0);

if(!function_exists('msg_queue_exists'))
	{
	// msg_queue_exists() added to php in v. 5.3.0
	function msg_queue_exists($key)
		{
		$q = array();
		$kx=sprintf('%x',$key);
		exec("/usr/bin/ipcs -q | /bin/grep \"^[0-9]\" | /usr/bin/cut -d \" \" -f 1", $q);
		return strstr($q[0],$kx);
		}
	}

class ipc_message_queue
	{
	private $msgqkey = 1;
	private $msgtype = 1;

	private $q_uid = 0;
	private $q_gid = 0;
	private $q_mode = '0666';

	public function __construct($k, $t, $uid=0, $gid=0, $mode='0666')
		{
		$this->msgqkey = $k;
		$this->msgtype = $t;
		$this->q_uid   = $uid;
		$this->q_gid   = $gid;
		$this->q_mode  = $mode;
		$this->getqueue(MSG_Q_ALWAYS_CREATE);
		}

	public function getqueue($create=false)
		{
		$q = null;
		$exists = msg_queue_exists($this->msgqkey);
		logmsg(WARN,'Queue does'.($exists?'':' NOT').' exist; key='.$this->msgqkey,__FILE__,__LINE__);
		if($create or $exists)
			{
			// linux: needs CAP_IPC_OWNER;
			// if not root, then (setcap is in package libcap2-bin)
			// > setcap cap_ipc_owner=ep /usr/bin/php5
			$q = msg_get_queue($this->msgqkey/*, $this->q_mode*/);
			if($q==null)
				{
				logmsg(FATAL,'get or create Queue FAILED',__FILE__,__LINE__);
				}
			msg_set_queue($q, array
				(
				'msg_perm.uid' =>$this->q_uid,
				'msg_perm.gid' =>$this->q_gid,
				'msg_perm.mode'=>$this->q_mode,
				));
			}
		else
			logmsg(FATAL,'Queue key='.$this->msgqkey.' not created',__FILE__,__LINE__);
		return $q; 
		}

	public function deletequeue()
		{
		// usually best not remove the queue, as other msg-types may be pending
		if(msg_queue_exists($this->msgqkey))
			{
			logmsg(WARN,'removing existing Queue and all it\'s pending messages, key='.$this->msgqkey,__FILE__,__LINE__);
			msg_remove_queue(msg_get_queue($this->msgqkey));
			}
		}

	public function process_messages()
		{
		global $G_MsgQStop;
		logmsg(DEBUG,'start',__FILE__,__LINE__);
		while(!$G_MsgQStop)
			{
			$this->process_queue_messages();
			}
		logmsg(DEBUG,'end',__FILE__,__LINE__);
		}

	public function process_queue_messages()
		{
		global $G_Crit_Sect;
		$err = NULL;
		$message = null;
		$msg_type_recvd = null;

		// though q-id won't change between invocations, it will fail if the Q has gone away..
		// $this->getqueue() must return a qID or cause a fatal error
		$qid = $this->getqueue();

		// blocking read
		if(msg_receive($qid, $this->msgtype, $msg_type_recvd, MSG_SIZE_MAX, $message, SERIALISE, MSG_NOERROR, $err))
			{
			switch($err)
				{
				case 0:
					logmsg(DEBUG,'Received Type='.$msg_type_recvd.', Msg='.$message,__FILE__,__LINE__);
					critical_section(true);
					try
						{
						$this->msg_received($msg_type_recvd,$message);
						}
					catch(Exception $e)
						{
						logmsg(ERROR,'Err handling msg : '.print_r($err,false),__FILE__,__LINE__);
						}
					critical_section(false);
					break;
				default:
					logmsg(ERROR,'error receiving message : '.self::geterr($err,'r'),__FILE__,__LINE__);
					break;
				}
			}
		else
			{
			// read failed; the ipc-queue was probably destroyed.
			// if MSG_Q_ALWAYS_CREATE==true, the next 'getqueue' call will try recreating the queue
			logmsg(ERROR,'read message FAILED:'.$err.'='.self::geterr($err,'r'),__FILE__,__LINE__);
			sleep(1);
			}
		}

	public function send_message($msg)
		{
		if(!is_string($msg))
			logmsg(ERROR,'ipc_message_queue::send_message: message must be a string, instead got: '.print_r($msg,true),__FILE__,__LINE__);
		else
			{
			$qid = $this->getqueue();
			if($qid)
				{
				$err = 0;
				logmsg(DEBUG,'ipc_message_queue::send_message $qid='.$qid.', type='.$this->msgtype.', message='.$msg,__FILE__,__LINE__);
				// blocking write (otherwise messages would probably be lost)
				msg_send($qid, $this->msgtype, $msg, SERIALISE, /*blocking?*/true, $err);
				if($err!=0)
					logmsg(ERROR,'ipc_message_queue::send_message err='.$err.', errmsg='.self::geterr($err,'w'),__FILE__,__LINE__);
				}
			}
		}

	protected function msg_received($type,$message)
		{
		logmsg(DEBUG,"ipc_message_queue::msg_received type=$type, message='$message'\n",__FILE__,__LINE__);
		}

	public static function geterr($errno,$rw='r')
		{
		return self::$errs[$errno][$rw];
		}

	private static $errs =  array(
		EACCES => array(
			's'=>'The calling process does not have write permission on the message queue, and does not have the CAP_IPC_OWNER capability.',
			'r'=>'The calling process does not have read permission on the message queue, and does not have the CAP_IPC_OWNER capability.'),
		EAGAIN => array(
			's'=>'The message can’t be sent due to the msg_qbytes limit for the queue and IPC_NOWAIT was specified in msgflg.',
			'r'=>'No message was available in the queue and IPC_NOWAIT was specified in msgflg.'),
		EFAULT => array(
			's'=>'The address pointed to by msgp isn’t accessible.',
			'r'=>'The address pointed to by msgp isn’t accessible.'),
		EIDRM => array(
			's'=>'The message queue was removed.',
			'r'=>'While the process was sleeping to receive a message, the message queue was removed.'),
		EINTR => array(
			's'=>'Sleeping on a full message queue condition, the process caught a signal.',
			'r'=>'While the process was sleeping to receive a message, the process caught a signal; see signal(7).'),
		EINVAL => array(
			's'=>'Invalid msqid value, or non-positive mtype value, or invalid msgsz value (less than 0 or greater than the system value MSGMAX).',
			'r'=>'msgqid was invalid, or msgsz was less than 0.'),
		ENOMEM => array(
			's'=>'The system does not have enough memory to make a copy of the message pointed to by msgp.',
			'r'=>'IPC_NOWAIT was specified in msgflg and no message of the requested type existed on the message queue.'),
		E2BIG => array(
			'r'=>'The message text length is greater than msgsz and MSG_NOERROR isn’t specified in msgflg.')
		);
	}

