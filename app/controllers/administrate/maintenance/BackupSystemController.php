<?php
/********************************************************************************/
/*                                                                              */
/*  /app/controllers/administrate/maintenance/BackupSystemController.php        */
/*  Bruce Klotz - PELHAMHS.ORG                                                  */
/*                                                                              */
/*  Requires:                                                                   */
/*  /app/config/local/backup.conf                                               */
/*  /app/controllers/administrate/maintenance/BackupSystemController.php        */
/*  /themes/default/views/administrate/maintenance/backup_system_html.php       */
/*  /app/widgets/backup                                                         */
/*                                                                              */
/*  modified: navigation.conf, user_actions.conf                                */
/*                                                                              */
/********************************************************************************/
 
 
 
 define("__PHS_BACKUP_VERSION__","1.5.61");
 /* ----------------------------------------------------------------------
 * Manages backups of the system
 *
 * Requires /app/conf/backup.conf or /app/config/local/backup.config
 *
 * ----------------------------------------------------------------------
 */
  
 
class BackupSystemController extends ActionController {

	# ------------------------------------------------	
	public function __construct(&$po_request, &$po_response, $pa_view_paths=null) {
		parent::__construct($po_request, $po_response, $pa_view_paths);
		
		if (!$this->request->isLoggedIn()) { // error is not logged in.
			$this->response->setRedirect($this->request->config->get('error_display_url').'/n/2320?r='.urlencode($this->request->getFullUrlPath()));
 			return;
		}		
	}
	# ------------------------------------------------
	public function Index(){
// Called by all requests
		
		$statusmessage = $message = $ret = "";	
	// Check for conf files, including /local and include them...or error.
			if ( is_file(__CA_CONF_DIR__.'/local/backup.conf')){ require_once( __CA_CONF_DIR__.'/local/backup.conf');}
      elseif (is_file(__CA_CONF_DIR__.'/backup.conf')){require_once( __CA_CONF_DIR__.'/backup.conf'); }
      else{
          $statusmessage = $message = "<div style='color:red;font-weight:bold;font-size:200%;text-align:center;'>****** ERROR!!!!! ******<br/>Backup System Controller Config files NOT FOUND:<br/><i> /app/config/backup.conf</i> or <i>/app/config/local/backup.conf</i></div>";
          define(__PHS_BACKUP_CONF_V__," <span style='color:red;'> ERROR Missing Config File! </span>");
          $this->view->setVar('message', $message);
 			    $this->render('backup_system_html.php');
		      return false;
		   }						
			
			$backuppath =__PHS_BACKUP_PATH__;
			$this->view->setVar('backuppath', $backuppath);
			$this->view->setVar('backupsystemver',__PHS_BACKUP_VERSION__);
			
			$this->view->setVar('restoreSQLpath',__PHS_BACKUP_RESTORE_SQL_DATABASE__);
			$this->view->setVar('backuprestorepath',__PHS_BACKUP_RESTORE_PATH__);
						
  // Set permissions
			$can_restore_system = FALSE; 
		  
		  // If users can restore delete or download
		  if ($this->request->user->canDoAction('can_restore_system')) {
				$can_restore_system = TRUE;
		  }
			$this->view->setVar('can_restore_system',$can_restore_system);

	    $can_upgrade_system = FALSE; 
		  // If users can upgrade the system
		  if ($this->request->user->canDoAction('can_upgrade_system')) {
				$can_upgrade_system = TRUE;
		  }
			$this->view->setVar('can_upgrade_system',$can_upgrade_system);			
	
	// If Toggling file listing [Show All]/[Hide]...
			if($showallfiles = $this->request->getParameter('showallfiles', pString)){
				if ($showallfiles == 1){$this->view->setVar('showallfiles', 0);}
				else{$this->view->setVar('showallfiles', 1);}
			}// if showallfiles
			
	// If Saving an existing backup fil...
			if($savefile = $this->request->getParameter('save', pString)){
				$fullfile = $backuppath."/".$savefile;
				$filetype = substr($savefile,-3,3);
			 	$message .= "<div><b>Saving </b> ".$savefile."<b>...";
			 		header("Content-disposition: attachment; filename=$savefile");
					header("Content-type: application/$filetype");
				       	// readfile($fullfile); 
					while(strlen($dafile)<=10 and $i<=1000){
       						$i++;
       						$dafile=  file_get_contents($fullfile);
       					}
       					echo $dafile;
					$message .= "</b></div>";
					exit;			
			}// if $savefile...
 			
	// If Deleting a file... 			
 			if ($filetodelete = $this->request->getParameter('delete', pString)){
 				$fullfile = $backuppath."/".$filetodelete;
 				$message = "<div><b>Deleting </b> ".$filetodelete."<b> ";
 				if(is_dir($fullfile)){system("rm -rf ".escapeshellarg($fullfile)); $message .= "[DIR]...<span style='color:green'>OK</span></b></div>";}
 				elseif(is_file($fullfile)){unlink($fullfile);@unlink($fullfile.".txt"); $message .= "[File]... <span style='color:green'>OK</span></b></div>";}
 				else{$message .= "FAILED!</b></div>";}
 				$statusmessage=$message;
 			}// if $filetodelete...
 			
	// If Backup... 			
 			if ($backup = $this->request->getParameter('backup', pString) or
 			    $upgradebackup = $this->request->getParameter('upgradebackup', pString)) {
 				set_time_limit(3600);
				$dbhost = __CA_DB_HOST__;
				$dbname = __CA_DB_DATABASE__;
				$dbuser = __PHS_BACKUP_USER__;
				$dbpass = __PHS_BACKUP_PASSWORD__;
				$nametag=date("Y-m-d-H-i-s");
				$databasefilename =__PHS_BACKUP_FILE_PRE__.$dbname . $nametag.".sql"; //Name of mySQL backup file.
				$configfilename = __PHS_BACKUP_FILE_PRE__.$dbname . $nametag.".tgz";  //Name of tarball to backup to.
				$backupNotes=  $this->request->getParameter('backupnotes', pString)." [V".__PHS_BACKUP_CONF_V__."]";
				if (isset($upgradebackup)){
				    $upgrademsg ="UPGRADE ";
				    $backupNotes = "UPGRADE BACKUP: $backupNotes";
				}else{$upgrademsg="";}
				
				$message .= "<b>Making ".$upgrademsg."Backup...</b><br/>
 				
 					     <b>Database: </b>$databasefilename : $ret<br/><b>Config: </b>$configfilename<br/>";
	                   if (!isset($upgradebackup)){
				// First dump mySQL database...	
				
				
				$tmpcrd ="[mysqldump]\n
                   host=$dbhost\n
                   user=$dbuser\n
                   password=$dbpass\n";
        $tmpcrdpath = tempnam(caGetTempDirPath(), 'tmpcrd');
        
        $tmpfile =fopen( $tmpcrdpath,"w");
		    fwrite($tmpfile,$tmpcrd);
		    fclose($tmpfile);
							
				$backupdatabaseFile = $backuppath."/".$databasefilename;
				//$command = "mysqldump --opt -K --no-tablespaces -h $dbhost --user=$dbuser --password=$dbpass $dbname > $backupdatabaseFile";		
			//	$command = "mysqldump --defaults-extra-file=$tmpcrdpath --opt -K --no-tablespaces $dbname > $backupdatabaseFile";		
			    $command = "mysqldump --defaults-extra-file=$tmpcrdpath --opt -K --no-tablespaces -h $dbhost --user=$dbuser --password=$dbpass $dbname > $backupdatabaseFile";	
				
				system($command);
				unlink($tmpcrdpath);
				$filesize = filesize($backupdatabaseFile);
				if(!is_file($backupdatabaseFile)){$statusmessage = "$message <b><font color='red'>SQL backup FAILED! - SQL File not written </font></b><br/>";}
				elseif($filesize > 54){$tmpstatusmessage = "<b>SQL Backup Made <span style='color:green'> OK</span></b> filesize: $filesize<br/>";}
				else{$statusmessage .= "$message <b><font color='red'>SQL backup FAILED! - SQL File dump EMPTY!</font></b><br/>".$command;}
				 	
				// Now append the SET FOREIGN KEY to both sides of the SQL file to allow innDb files to reload...
				
				
        //method 1:
				     //$backupcontents = "SET FOREIGN_KEY_CHECKS=0; \n".file_get_contents($backupdatabaseFile)."\nSET FOREIGN_KEY_CHECKS=1;\n";
				     //$ret = file_put_contents($backupdatabaseFile, $backupcontents);//, LOCK_EX);
				
				// Method 2: Command method because of memory issues:
				$backupdatabaseFiletmp = $backupdatabaseFile."tmp";
			  	//$command = "sed -i -e 's/^/SET FOREIGN_KEY_CHECKS=0;\n/' $backupdatabaseFile";
			  	//$command = "sed -i '0,/^/s//SET FOREIGN_KEY_CHECKS\=0; \n' $backupdatabaseFile";
			  	
			  	$command ="(echo 'SET FOREIGN_KEY_CHECKS=0;\n'; cat $backupdatabaseFile) > $backupdatabaseFiletmp && mv $backupdatabaseFiletmp $backupdatabaseFile";
			  	
		  		$statusmessage .= "$message".shell_exec($command);
			  	$command = "echo '\nSET FOREIGN_KEY_CHECKS=1;\n' >> $backupdatabaseFile";
		  		$statusmessage .= "".shell_exec($command);
					
				// Save the notes field for the SQL file to a text file...
				 $noteret = file_put_contents($backupdatabaseFile.".txt", $backupNotes, FILE_APPEND);// | LOCK_EX);
				 $statusmessage .= "$tmpstatusmessage";
			    }//if !$upgradebackup
			    
				//Now copy files into the backup tarball, including the .sql and notes .txt files...
				if(isset($upgradebackup)){
				    $backupfromlist = __PHS_UPGRADE_FILE_LIST__; //defined in backup.conf
				}else{
				     //$backupfromlist = __PHS_BACKUP_FILE_LIST__." $backupdatabaseFile $backupdatabaseFile.txt"; //defined in setup.php
				     $backupfromlist = __PHS_BACKUP_FILE_LIST_PHS__.__PHS_BACKUP_FILE_LIST_MODIFIED__." $backupdatabaseFile $backupdatabaseFile.txt"; //defined in setup.php
				}
				
				$backuptopath = $backuppath."/".$configfilename;
								
				//shell_exec("tar -Pcvzf ".$backuptopath." ".$backupfromlist);// $X="L111 $backupfromlist";
				shell_exec("tar -C / -Pcvzf ".$backuptopath." ".$backupfromlist);// $X="L111 $backupfromlist";
				
				// Save the notes field for the tarball file to a text file...
				$noteret = file_put_contents($backuptopath.".txt", $backupNotes, FILE_APPEND);// | LOCK_EX);
					
		 		//$this->view->SetVar('backuppath',$backuppath);
	 			
 				//$last = shell_exec("tar -Pztf $backuptopath");  //maybe limit directory structure in .tgz
 				$last = shell_exec("tar -C / -Pztf $backuptopath");  //maybe limit directory structure in .tgz
 				if(strlen($last) > 1){
 					$statusmessage .= "<b>Tarball Backup <span style='color:green'>OK</span></b> filesize: ".filesize($backuptopath)."<br/>";
				}else{$statusmessage .= "<b><font color='red'>Tarball Backup FAILED!</font></b><br/>";}
				$message = $statusmessage."<div style='font-size:6pt'>".str_replace("\n","<br/>",$last)."</div>";			 		
 			}//if backup
 
 	// If Restore...
 			if ($filetorestore = $this->request->getParameter('restore', pString)) {
 				$message .= "<b>Restoring...</b>";
 				set_time_limit(3600);
				$dbhost = __CA_DB_HOST__;
				$dbname = __PHS_BACKUP_RESTORE_SQL_DATABASE__; //__CA_DB_DATABASE__;
				$dbuser = __PHS_BACKUP_USER__;
				$dbpass = __PHS_BACKUP_PASSWORD__;
				$fullfiletorestore = $backuppath."/".$filetorestore;
								 
				// If restoring a sgl file
				if  (substr("$fullfiletorestore", -3) == "sql"){
					$message .="<br/><b>Database: </b>$filetorestore : ";
					$command = "mysql -v --user=$dbuser --password=$dbpass -h $dbhost --database=$dbname < $fullfiletorestore";	
					
					error_log("/app/controllers/administrate/maintenance/BackupSystemController.php L163 command: $command");
					$last = shell_exec($command); 
					$last = "<b>SQL Length:</b> ".strlen($last); // Return length instead of the entire sql
					$message .= "<div style='font-size:6pt'>".str_replace("\n","<br/>",$last)."</div>";	
					$message .= "<b><span style='color:green'>OK</span></b><br/>";		 
				} // if sql...
				
				// If restoring a tarball
				if  (substr("$fullfiletorestore", -3) == "tgz"){
					$message .= "<br/><b>Config: </b> $filetorestore :<br/> ";
					$backupfromfile = $backuppath."/".$filetorestore;
					$restorepath = __PHS_BACKUP_RESTORE_PATH__;
				 	$last = shell_exec("tar --overwrite --strip-components=5 --show-transformed-names -xvzf $backupfromfile -C $restorepath");
					$message .= "<div style='font-size:6pt'>".str_replace("\n","<br/>",$last)."</div>";	
					$message .= "<b><span style='color:green'>OK</span></b><br/>";		 
				}// if tarball			
 			}//if filetorestore
 			
 			// If buildgitrestore..
 			if ($buildgitrestore = $this->request->getParameter('buildgitrestore', pString)) {
 				$message .= "<b>Building Git Restore Package...</b>";
 				set_time_limit(3600);
 				$dbname = __CA_DB_DATABASE__;
				$nametag=date("Y-m-d-H-i-s");
				$configfilename = __PHS_BACKUP_FILE_PRE__.$dbname . $nametag."-gitpackage.tgz";  //Name of tarball to backup to.
				$backupNotes =  "Git Package:".$this->request->getParameter('backupnotes', pString);
				$backuptopath = $backuppath."/".$configfilename;
				$noteret = file_put_contents($backuptopath.".txt", $backupNotes, FILE_APPEND);// | LOCK_EX);
				$backupfromlist = __PHS_BACKUP_GIT_LIST__; //defined in setup.php
				
				//error_log("/app/controllers/administrate/maintenance/BackupSystemController.php L190 backuptopath: $backuptopath");
				//$last=shell_exec("tar -cvzf ".$backuptopath." ".$backupfromlist); 
				$last=shell_exec("tar -C / -cvzf ".$backuptopath." ".$backupfromlist);
				$message .= "<div style='font-size:6pt'>".str_replace("\n","<br/>",$last)."</div>--";	
				$message .= "<b><span style='color:green'>OK</span></b><br/>";		 
				//		foreach(explode(" ",$gitfilelist) as $filetoextract){
 			}//if gitrestore
 			
 			$this->view->setVar('message', $message);
 			$this->view->setVar('statusmessage', $statusmessage);
 			$this->render('backup_system_html.php');
	}//function index	
}//class
?>