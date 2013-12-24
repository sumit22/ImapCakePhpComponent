<?php

/**
 * @desc : A cake php component which encapsulate some basic imap related functionality
 * @created by : Sumit Kumar
 * @created on : 04-03-2013
 * 
 */
App::uses('Component', 'Controller');
class ImapComponent extends Component {

    public $components = array();
    public $settings = array();
    //resource type
    public $imapStream = null;
    //imap host server
    private $hostName = "";
    //user name for the mail account
    private $userName = "";
    //password for the mail account
    private $password = "";
    
    
    public $dataTypes = array(
        0 => 'text',
        1 => 'multipart',
        2 => 'message',
        3 => 'application',
        4 => 'audio',
        5 => 'image',
        6 => 'video',
        7 => 'other',
    );
    public $encodingTypes = array(
        0 => '7bit',
        1 => '8bit',
        2 => 'binary',
        3 => 'base64',
        4 => 'quoted-printable',
        5 => 'other',
    );

    function initialize(&$controller, $settings) {
        $this->controller = $controller;
        $this->settings = $settings;
    }

    function startup(&$controller) {
        
    }

    function shutDown(&$controller) {
        
    }

    /**
     * @desc : class constructor setting class variable imapStream
     * 
     * @params : 1. String $hostName : IMAP serer details
     *           2. String $userName : userName of mail account
     *           3. String $password : Password for mail account
     * 
     * @created by : Sumit Kumar
     * @created on : 04-03-2013
     */
    public function __construct($hostName = null, $userName = null, $password = null) {
        $collection = new ComponentCollection;
        parent::__construct($collection);
        //if user supplied creadentails then create stream with those credential
        if ($hostName && $userName && $password) {

            $this->hostName = $hostName;
            $this->userName = $userName;
            $this->password = $password;

            $this->imapStream = $this->getIMAPStream($this->hostName, $this->userName, $this->password);
        } else { //create default stream
            $this->imapStream = $this->getIMAPStream();
        }
    }

    function __destruct() {

        if (is_resource($this->imapStream)) {
            imap_close($this->imapStream);
        }
    }

    /**
     * @desc : functiobn to retrieve the IMAP stream from an IMAP server
     * 
     * @params : 1. String $hostName : IMAP serer details
     *           2. String $userName : userName of mail account
     *           3. String $password : Password for mail account
     * 
     * @return : resource type : resource id else false
     * @access : private
     * 
     * @created by : Sumit Kumar
     * @created on : 04-03-2013
     * 
     */
    private function getIMAPStream($hostName = '{XXX.XXX.XXX.XXX:port/pop3}INBOX', $userName = 'username', $password = 'password*') {
        $inbox = imap_open($hostName, $userName, $password) or die('Cannot connect to MailServer: ' . imap_last_error());
        
        if ($inbox) {

            return $inbox;
        } else {
            return false;
        }
    }

    /**
     * @desc : function return an array of message ids which are yet not seen
     * @params : N/A
     * @return : int[] of messageIds created by server
     * 
     * 
     * @created by : Sumit Kumar
     * @created on : 04-03-2013
     */
    public function getUnseenMessageIds() {

        return imap_search($this->imapStream, 'UNSEEN');
    }

    public function markAsSeen($messageId) {
        //imap_setflag_full ( resource $imap_stream , string $sequence , string $flag [, int $options = NIL ] )
        imap_setflag_full($this->imapStream, $messageId, "\SEEN", FT_UID);
    }

    public function markMessageForDeletion($messageId) {
        imap_delete($this->imapStream, $messageId);
    }

    function deleteMessage() {
        imap_expunge($this->imapStream);
    }

    public function getRecentMessages($since = 5, $unit = 'days') {
        $date = date("d M Y", strToTime("-" . $since . " " . $unit));
        return imap_search($this->imapStream, "SINCE \"$date\"", SE_UID);
        //return imap_search($this->imapStream, 'UNSEEN');
    }

    /**
     * @desc : wrapper for imap_fetch_overview() with basic parameters
     * @params : int $messageId
     * @return : mixed[] messageOverview
     * @access : public
     * 
     * @created by : Sumit Kumar
     * @created on : 04-03-2013
     */
    public function getMessageOverview($messageId) {
        return imap_fetch_overview($this->imapStream, $messageId, FT_UID);
    }

    /**
     * @desc : retrieves subject line of the message from its overview part
     * @params : mixed[] $messageOverView
     * @return : String messageOverview
     * @access : public
     * 
     * @created by : Sumit Kumar
     * @created on : 04-03-2013
     */
    public function getMessageSubjectFromOverview($messageOverView) {
        //$output.= '' . $overview[0]->subject
        return $messageOverView[0]->subject;
    }

    /**
     * @desc : retrives subject line of the message from its messageId
     * @params : 1. int $messageId
     * 
     * @return : String subject line of the message
     * 
     * @created by : Sumit Kumar
     * @created on : 04-03-2013
     */
    public function getMessageSubjectById($messageId) {
        return $this->getMessageSubjectFromOverview($this->getMessageOverview($messageId));
    }

    function getMessageStucture($messageId) {

        return imap_fetchstructure($this->imapStream, $messageId, FT_UID);
    }

    /**
     * @desc : encapsulates imap_fetchbody() with basic params
     * @params : 1. int $messageId
     *           2. String $section
     * @return : String body of the message contained in the particular section
     * @access : public
     * 
     * @created by : Sumit Kumar
     * @created on : 07-03-2013
     * 
     */
    public function getMessageBody($messageId, $section = "1.2", $option = FT_UID) {

        return imap_fetchbody($this->imapStream, $messageId, $section, $option);
    }

    /**
     * @desc : returns all details and data of all the attachments in a mail
     * @params : 1. Obj $messageStructure
     *           2. int $messageId
     * @access : public
     * @return : mixed[] $attachments
     * 
     * @created by : Sumit Kumar
     * @created on : 07-03-2013
     * 
     */
    public function getAttachementsFromMessageStructure1($messageStructure, $messageId) {
        $attachments = false;
        
        
        $messageParts = $messageStructure->parts;
        $fpos = 2;

        for ($i = 1; $i < count($messageParts); $i++) {
            $messagePart = $messageParts[$i];
            if (!isset($messagePart->disposition)) {
                $fpos+=1;
                continue;
            }
            
            if ($messagePart->disposition == "ATTACHMENT" && ($messagePart->ifdparameters || $messagePart->parameters)) {
                
                $fileName = ($messagePart->ifdparameters) ? $messagePart->dparameters[0]->value : $messagePart->parameters[0]->value;
                $attachment = array(
                    'bytes' => $messagePart->bytes,
                    'tmpFilePath' => TMP . uniqid("lin_resume-") . "-" . $fileName,
                    'subtype' => $messagePart->subtype,
                    'fileName' => uniqid("lin_resume-") . "-" . $fileName
                );

                
                $tmpFile = new File($attachment['tmpFilePath']);
                $messageBody = imap_fetchbody($this->imapStream, $messageId, $fpos);

                $attachmentFileData = $this->getAttachmentsData($messageBody, $messagePart->type);

                if ($attachmentFileData) {
                    $attachment['content'] = $attachmentFileData;
                    $attachment['ext'] = $tmpFile->ext();
                    $attachment['contentType'] = $this->getMimeTypeByExtension($attachment['ext']);
                    //files will be created only after validation
                    //$tmpFile = new File($attachment[tmpFilePath],true,'777');
                    //$tmpFile->write($attachmentFileData,'w',true);

                    $tmpFile->delete();
                } else {
                    $fpos+=1;
                    continue;
                }

                $attachments[] = $attachment;
            }
            $fpos+=1;
        }
        
        
        
        
        return $attachments;
    }
    
    function getAttachementsFromMessageStructure($messageId){
        
        return $this->getAttachmentsFromEmailByMessageId($messageId);
    }
    
    public function getAttachmentsFromEmailByMessageId($messageId){
        $flatStructure = $this->_flatStructure($messageId);
        $attachments = $this->_fetchAttachments($flatStructure);
        
        $attachmentsForProcessing = false;
        if(count($attachments)) {
            $attachmentsForProcessing = array();
            foreach($attachments as $attachment) { 
                
                
                $attachmentNew = array(
                    'bytes' => $attachment['size'],
                    'tmpFilePath' => TMP . uniqid("lin_resume-") . "-" . $attachment['name'],
                    'subtype' => $attachment['type'],
                    'fileName' => uniqid("lin_resume-") . "-" . $attachment['name']
                );
                $tmpFile = new File($attachmentNew['tmpFilePath']);
                
                $attachmentFileData = $this->getAttachmentsData($attachment['attachment'], $attachment['part_type']);
                $attachmentNew['content'] = $attachmentFileData;
                $attachmentNew['ext'] = $tmpFile->ext();
                $attachmentNew['contentType'] = $this->getMimeTypeByExtension($attachmentNew['ext']);
                $tmpFile->delete();
                
                $attachmentsForProcessing[] = $attachmentNew;
            }
        }
        
        return $attachmentsForProcessing;
    }
    
    
    
    public function getAttachmentsAsUploadedFiles($attachments,$fileNamePrefix="") {
        $attachmentsForProcessing = false;
        if(count($attachments)) {
            $attachmentsForProcessing = array();
            foreach($attachments as $attachment) { 
                $fileName = ($attachment['name']!="")?$attachment['name']:$attachment['filename'];
                if(!$fileName) {
                    $atTemp = $attachment;
                    unset($atTemp['attachment']);
                    echo json_encode($atTemp);
                }
                $attachmentNew = array(
                    'bytes' => $attachment['size'],
                    'tmpFilePath' => TMP . uniqid($fileNamePrefix) . "" . $fileName,
                    'subtype' => $attachment['type'],
                    'fileName' => uniqid($fileNamePrefix) . "" . $fileName
                );
                $tmpFile = new File($attachmentNew['tmpFilePath']);
                
                $attachmentFileData = $this->getAttachmentsData($attachment['attachment'], $attachment['part_type']);
                $attachmentNew['content'] = $attachmentFileData;
                $attachmentNew['ext'] = $tmpFile->ext();
                
                if((!$attachmentNew['ext'])&&($attachment['mime_type'])){
                    $attachmentNew['ext'] = $this->getExtensionByMimeType($attachment['mime_type']);
                    //debug($attachmentNew['ext']);
                    $attachmentNew['tmpFilePath'].=".".$attachmentNew['ext'];
                    $attachmentNew['fileName'].=".".$attachmentNew['ext'];
                    //print_r($attachmentNew);
                    
                }
                $attachmentNew['contentType'] = $this->getMimeTypeByExtension($attachmentNew['ext']);
                $tmpFile->delete();
                
                $attachmentsForProcessing[] = $attachmentNew;
            }
        }
        
        return $attachmentsForProcessing;
        
        
    }
    
    public function getFormattedMailById($messageId,$fetchAttachments=false,$formatAttachment=false) {
        // Translate uid to msg_no. Has no decent fail
                $Model = "Imap";
		$msg_number = imap_msgno($this->imapStream, $messageId);
                //debug($msg_number);
		// A hack to detect if imap_msgno failed, and we're in fact looking at the wrong mail
		if ($messageId != ($mailuid = imap_uid($this->imapStream, $msg_number))) {
			//pr(compact('Mail'));
                        return false;
//                        
//			return $this->err(
//				$Model,
//				'Mail id mismatch. parameter id: %s vs mail id: %s',
//				$messageId,
//				$mailuid
//			);
		}
                $overView = imap_fetch_overview($this->imapStream, $messageId, FT_UID);
		if (!($Mail = imap_header($this->imapStream, $msg_number)) || !property_exists($Mail, 'date')) {
			//pr(compact('Mail'));
                        return false;
//			return $this->err(
//				$Model,
//				'Unable to find mail date property in Mail corresponding with uid: %s. Something must be wrong',
//				$messageId
//			);
		}
                
		// Get Mail with a property: 'type' or fail
		if (!($flatStructure = $this->_flatStructure($messageId))) {
                        return false;
//			return $this->err(
//				$Model,
//				'Unable to find structure type property in Mail corresponding with uid: %s. Something must be wrong',
//				$messageId
//			);
		}

		$plain = $this->_fetchFirstByMime($flatStructure, 'text/plain');
		$html  = $this->_fetchFirstByMime($flatStructure, 'text/html');
		$return["MailObject"] = array(
			'id' => $messageId,
			'message_id' => $Mail->message_id,
			'email_number' => $Mail->Msgno,

			'to' => $this->_personId($Mail, 'to', 'address'),
			'to_name' => $this->_personId($Mail, 'to', 'name'),
			'from' => $this->_personId($Mail, 'from', 'address'),
			'from_name' => $this->_personId($Mail, 'from', 'name'),
			'reply_to' => $this->_personId($Mail, 'reply_to', 'address'),
			'reply_to_name' => $this->_personId($Mail, 'reply_to', 'name'),
			'sender' => $this->_personId($Mail, 'sender', 'address'),
			'sender_name' => $this->_personId($Mail, 'sender', 'name'),

			'subject' => htmlspecialchars(@$Mail->subject),
			'slug' => Inflector::slug(@$Mail->subject, '-'),
			'header' => @imap_fetchheader($this->Stream, $messageId, FT_UID),
			'body' => $html,
			'plainmsg' => $plain ? $plain : $html,
			'size' => @$Mail->Size,

			'recent' => @$Mail->Recent === 'R' ? 1 : 0,
			'seen' => @$Mail->Unseen === 'U' ? 0 : 1,
			'flagged' => @$Mail->Flagged === 'F' ? 1 : 0,
			'answered' => @$Mail->Answered === 'A' ? 1 : 0,
			'draft' => @$Mail->Draft === 'X' ? 1 : 0,
			'deleted' => @$Mail->Deleted === 'D' ? 1 : 0,

			//'thread_count' => $this->_getThreadCount($Mail),
			'in_reply_to' => @$Mail->in_reply_to,
			'reference' => @$Mail->references,
			'new' => (int)@$Mail->in_reply_to,
			'created' => date('Y-m-d H:i:s', strtotime($Mail->date)),
		);

		if ($fetchAttachments) {
			$return['Attachment'] = $this->_fetchAttachments($flatStructure);
                        
                        if($formatAttachment && count($return['Attachment'])) {
                            
                            $return['Attachment'] = $this->getAttachmentsAsUploadedFiles($return['Attachment']);
                        }
		}

		// Auto mark after read
//		if (!empty($this->config['auto_mark_as'])) {
//			$marks = '\\' . join(' \\', $this->config['auto_mark_as']);
//			if (!imap_setflag_full($this->Stream, $messageId, $marks, ST_UID)) {
//				//$this->err($Model, 'Unable to mark email %s as %s', $messageId, $marks);
//			}
//		}

		return $return;
    }

    

    /**
     * @desc : based on the partType of a given of message structure return the content of mail in proper encoding
     * @params : String $messageBody
     * @params : int $partType
     * @access : public
     * @return : String $messageBody, of the attachment
     * 
     * @created by : Sumit Kumar
     * @created on : 07-03-2013
     * 
     */
    function getAttachmentsData($messageBody, $partType) {

        if ($partType == 0) {
            $messageBody = imap_8bit($messageBody);
        } elseif ($partType == 1) {
            $messageBody = imap_8bit($messageBody);
        } elseif ($partType == 2) {
            $messageBody = imap_binary($messageBody);
        } elseif ($partType == 3) {
            $messageBody = imap_base64($messageBody);
        } elseif ($partType == 4) {
            $messageBody = imap_qprint($messageBody);
        } elseif ($partType == 5) {
            $messageBody = imap_base64($messageBody);
        }
        return $messageBody;
    }

    /**
     * @desc : checks if a given mail has attachments with it
     * @params : Obj $messageStructure as returned from imap_fetch_structure()
     * @return : bool true if has attachement else false
     * @access : public
     * 
     * @created by : Sumit Kumar
     * @created on : 07-03-2013 
     */
    public function hasAttachments($messageStructure) {

        $hasAttachments = false;

        foreach ($messageStructure->parts as $messagePart) {

            if (!isset($messagePart->disposition)) {
                continue;
            }
            if ($messagePart->disposition == "ATTACHMENT") {

                return $hasAttachment = true;
            }
        }


        return $hasAttachments;
    }

    /**
     * @desc : fetches the mail content's HTML Part
     * @params : 1. int $messageId
     * @return : String mails HTML
     * @access : public
     * 
     * @created by : Sumit Kumar
     * @created on : 07-03-2013
     * 
     */
    public function getMailText($messageId) {
        $messageStructure = $this->getMessageStucture($messageId);

        $text = false;
        if (is_object($messageStructure) && ($messageStructure->parts)) {

            foreach ($messageStructure->parts as $messageKey => $messageValue) {


                if (($messageValue->subtype == 'HTML') || ($messageValue->subtype == 'PLAIN')) {
                    if ($messageValue->subtype == 'HTML') {

                        $text['html'] = imap_fetchbody($this->imapStream, $messageId, ($messageKey + 1));
                    }
                }
            }
        }

        return $text;
    }

    /**
     * @desc : returns the emailId of the sender from the mail
     * @params : Obj $overview returned by imap_fetch_overview()
     * 
     * @return : String emailId of sender
     * @access : public
     * @created by : Sumit Kumar
     * @created on : 07-03-2013
     */
    public function getSenderId($overview) {
        $rawSender = $overview[0]->from;


        $rawSender = explode("<", $rawSender);

        $rawSender = str_replace(">", "", $rawSender);


        return $rawSender[1];
    }

    /**
     * @desc : returns the MIME type for a given file extension [for some common/general file extension]
     * @params : 1. String $ext extension for which MIME type has to be determined, [must be passed without dot(.)]
     * @return : String $mimeType if found in lookUp array else false
     * @access : public
     * 
     * @created by : Sumit Kumar
     * @created on : 11-03-2013
     * 
     */
    public function getMimeTypeByExtension($ext) {
        $mimeTypes = array(
            'aif' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'avi' => 'video/avi',
            'bmp' => 'image/bmp',
            'bz2' => 'application/x-bz2',
            'csv' => 'text/csv',
            'dmg' => 'application/x-apple-diskimage',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'eml' => 'message/rfc822',
            'aps' => 'application/postscript',
            'exe' => 'application/x-ms-dos-executable',
            'flv' => 'video/x-flv',
            'gif' => 'image/gif',
            'gz' => 'application/x-gzip',
            'hqx' => 'application/stuffit',
            'htm' => 'text/html',
            'html' => 'text/html',
            'jar' => 'application/x-java-archive',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'm3u' => 'audio/x-mpegurl',
            'm4a' => 'audio/mp4',
            'mdb' => 'application/x-msaccess',
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'odg' => 'vnd.oasis.opendocument.graphics',
            'odp' => 'vnd.oasis.opendocument.presentation',
            'odt' => 'vnd.oasis.opendocument.text',
            'ods' => 'vnd.oasis.opendocument.spreadsheet',
            'ogg' => 'audio/ogg',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ps' => 'application/postscript',
            'rar' => 'application/x-rar-compressed',
            'rtf' => 'application/rtf',
            'tar' => 'application/x-tar',
            'sit' => 'application/x-stuffit',
            'svg' => 'image/svg+xml',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'ttf' => 'application/x-font-truetype',
            'txt' => 'text/plain',
            'vcf' => 'text/x-vcard',
            'wav' => 'audio/wav',
            'wma' => 'audio/x-ms-wma',
            'wmv' => 'audio/x-ms-wmv',
            'xls' => 'application/excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xml' => 'application/xml',
            'zip' => 'application/zip'
        );



        $ext = strtolower($ext);

        if (array_key_exists($ext, $mimeTypes)) {
            return $mimeTypes[$ext];
        } else {
            return false;
        }
    }

    
    /**
     * retrieves extension of files of common mime type
     * @param String $mimeType Mime Type of the file for which extension has to be determined
     * @return String extension name
     * @author Sumit Kumar <sumit.kumar@gmail.com>
     * 
     */
    function getExtensionByMimeType($mimeType){
        
        $mimeTypes = array(
            'aif' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'avi' => 'video/avi',
            'bmp' => 'image/bmp',
            'bz2' => 'application/x-bz2',
            'csv' => 'text/csv',
            'dmg' => 'application/x-apple-diskimage',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'eml' => 'message/rfc822',
            'aps' => 'application/postscript',
            'exe' => 'application/x-ms-dos-executable',
            'flv' => 'video/x-flv',
            'gif' => 'image/gif',
            'gz' => 'application/x-gzip',
            'hqx' => 'application/stuffit',
            'htm' => 'text/html',
            'html' => 'text/html',
            'jar' => 'application/x-java-archive',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'm3u' => 'audio/x-mpegurl',
            'm4a' => 'audio/mp4',
            'mdb' => 'application/x-msaccess',
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'odg' => 'vnd.oasis.opendocument.graphics',
            'odp' => 'vnd.oasis.opendocument.presentation',
            'odt' => 'vnd.oasis.opendocument.text',
            'ods' => 'vnd.oasis.opendocument.spreadsheet',
            'ogg' => 'audio/ogg',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ps' => 'application/postscript',
            'rar' => 'application/x-rar-compressed',
            'rtf' => 'application/rtf',
            'tar' => 'application/x-tar',
            'sit' => 'application/x-stuffit',
            'svg' => 'image/svg+xml',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'ttf' => 'application/x-font-truetype',
            'txt' => 'text/plain',
            'vcf' => 'text/x-vcard',
            'wav' => 'audio/wav',
            'wma' => 'audio/x-ms-wma',
            'wmv' => 'audio/x-ms-wmv',
            'xls' => 'application/excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xml' => 'application/xml',
            'zip' => 'application/zip'
        );
        $lMimeType = strtolower($mimeType);
        $mimeTypeToExt = array_flip(array_unique($mimeTypes));
        if (array_key_exists($lMimeType, $mimeTypeToExt)) {
            return $mimeTypeToExt[$lMimeType];
        } else {
            return false;
        }
        
    }

    //experimental codes begin
    
    
    
    
    
    
    
    public function _fetchAttachments($flatStructure) {
        $attachments = array();
        foreach ($flatStructure as $path => $Part) {
            if (!$Part->is_attachment) {
                continue;
            }
            
            $attachments[] = array(
                'message_id' => $Part->uid,
                'is_attachment' => $Part->is_attachment,
                'filename' => $Part->filename,
                'mime_type' => $Part->mimeType,
                'type' => strtolower($Part->subtype),
                'part_type'=>$Part->type,
                'datatype' => $Part->datatype,
                'format' => $Part->format,
                'name' => $Part->name,
                'size' => $Part->bytes,
                'attachment' => $this->_fetchPart($Part),
            );
        }

        return $attachments;
    }

    protected function _fetchPart($Part) { 
        $data = imap_fetchbody($this->imapStream, $Part->uid, $Part->path, FT_UID | FT_PEEK);
        if ($Part->format === 'quoted-printable' && $data) {
            $data = quoted_printable_decode($data);
        }
        
        
        return $data;
    }
    
    protected function _fetchFirstByMime($flatStructure, $mime_type) {
        foreach ($flatStructure as $path => $Part) {
            if ($mime_type === $Part->mimeType) {
                return $this->_fetchPart($Part);
            }
        }
    }
    
    protected function _flatStructure($uid, $Structure = false, $partnr = 1) {
        $mainRun = false;
        if (!$Structure) {
            $mainRun = true;
            $Structure = imap_fetchstructure($this->imapStream, $uid, FT_UID);
            if (!property_exists($Structure, 'type')) {
                return false;
                //return $this->err('No type in structure');
            }
        }
        $flatParts = array();

        if (!empty($Structure->parts)) {
            $decimas = explode('.', $partnr);
            $decimas[count($decimas) - 1] -= 1;
            $Structure->path = join('.', $decimas);
        } else {
            $Structure->path = $partnr;
        }
        $flatParts[$Structure->path] = $this->_awesomePart($Structure, $uid);

        if (!empty($Structure->parts)) {
            foreach ($Structure->parts as $n => $Part) {
                if ($n >= 1) {
                    $arr_decimas = explode('.', $partnr);
                    $arr_decimas[count($arr_decimas) - 1] += 1;
                    $partnr = join('.', $arr_decimas);
                }
                $Part->path = $partnr;

                $flatParts[$Part->path] = $this->_awesomePart($Part, $uid);

                if (!empty($Part->parts)) {
                    if ($Part->type == 1) {
                        $flatParts = Set::merge(
                                        $flatParts, $this->_flatStructure($uid, $Part, $partnr . '.' . ($n + 1))
                        );
                    } else {
                        foreach ($Part->parts as $idx => $Part2) {
                            $flatParts = Set::merge(
                                            $flatParts, $this->_flatStructure($uid, $Part2, $partnr . '.' . ($idx + 1))
                            );
                        }
                    }
                }
            }
        }

        // Filter mixed
        if ($mainRun) {
            foreach ($flatParts as $path => $Part) {
                if ($Part->mimeType === 'multipart/mixed') {
                    unset($flatParts[$path]);
                }
                if ($Part->mimeType === 'multipart/alternative') {
                    unset($flatParts[$path]);
                }
                if ($Part->mimeType === 'multipart/related') {
                    unset($flatParts[$path]);
                }
                if ($Part->mimeType === 'message/rfc822') {
                    unset($flatParts[$path]);
                }
            }
        }

        // Flatten more (remove childs)
        if ($mainRun) {
            foreach ($flatParts as $path => $Part) {
                unset($Part->parts);
            }
        }
        
        return $flatParts;
    }
    
    
    
    protected function _awesomePart($Part, $uid) {
        if (!($Part->format = @$this->encodingTypes[$Part->type])) {
            $Part->format = $this->encodingTypes[0];
        }

        if (!($Part->datatype = @$this->dataTypes[$Part->type])) {
            $Part->datatype = $this->dataTypes[0];
        }

        $Part->mimeType = strtolower($Part->datatype . '/' . $Part->subtype);

        $Part->is_attachment = false;
        $Part->filename = '';
        $Part->name = '';
        $Part->uid = $uid;

        if ($Part->ifdparameters) {
            foreach ($Part->dparameters as $Object) {
                if (strtolower($Object->attribute) === 'filename') {
                    $Part->is_attachment = true;
                    $Part->filename = $Object->value;
                }
            }
        }

        if ($Part->ifparameters) {
            foreach ($Part->parameters as $Object) {
                if (strtolower($Object->attribute) === 'name') {
                    $Part->is_attachment = true;
                    $Part->name = $Object->value;
                }
            }
        }

        if (false !== strpos($Part->path, '.')) {
            #$Part->is_attachment = true;
        }

        return $Part;
    }
    
    
    /**
	 * Tries to parse mail & name data from Mail object for to, from, etc.
	 * Gracefully degrades where needed
	 *
	 * Type: to, from, sender, reply_to
	 * Need: box, name, host, address, full
	 *
	 * @param object $Mail
	 * @param string $type
	 * @param string $need
	 *
	 * @return mixed string or array
	 */
	protected function _personId ($Mail, $type = 'to', $need = null) {
		if ($type === 'sender' && !isset($Mail->sender)) {
			$type = 'from';
		}

		$info['box'] = '';
		if (isset($Mail->{$type}[0]->mailbox)) {
			$info['box'] = $Mail->{$type}[0]->mailbox;
		}
		$info['name'] = $info['box'];
		if (isset($Mail->{$type}[0]->personal)) {
			$info['name'] = $Mail->{$type}[0]->personal;
		}

		$info['host'] = '';
		if (isset($Mail->{$type}[0]->host)) {
			$info['host'] = $Mail->{$type}[0]->host;
		}

		$info['address'] = '';
		if ($info['box'] && $info['host']) {
			$info['address'] = $info['box'] . '@' . $info['host'];
		}

		$info['full'] = $info['address'];
		if ($info['name']) {
			$info['full'] = sprintf('"%s" <%s>', $info['name'], $info['address']);
		}

		if ($need !== null) {
			return $info[$need];
		}

		return $info;
	}
        
        
        
        
        
        
        
        /**
         * 
         * just for testing and to find spome workarounds for imap_headerinfo
         */
        function diffFunctions($messageId) {
            
            $d1 = imap_headerinfo($this->imapStream, $messageId);
            
            
            //$dTemp = 
            $hText = imap_fetchbody($this->imapStream, $messageId, '0', FT_UID); 
            $d2 = imap_rfc822_parse_headers($hText);
            
            debug($d1); debug($d2);
            
            
            
        }

}
