<?php 
/*	
	Project Name: PSTAHL - Php Static Html File Generator and Image manager
	Author: Joey Albert Abano
	Open Source Resource: GITHub

	The MIT License (MIT)

	Copyright (c) 2015-2017 Joey Albert Abano		

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	------------------------------------------------------------------------------------------------------------------

	It's a good thing that you started reading this section, since most likely this is the only file you'll be 
	modifying and opening. This project aims to create a simplified php static html builder

	Below are the list of the basic components and functions that this project are capable of.
	1. Uses sqlite, as a built in flat file database 
	2. Basic blog functions
	3. Standard blog development console		
	4. Export build, generate html files and directories. Create the zip file.
	5. Creating templates
	
	Dependencies
	1. PHP 5 >= 5.3.0, PHP 7
	2. jquery version 2.1.4
	3. jqueryui version 1.11.4
	4. ckeditor version 4 full editor	
	5. datatables
	6. bootstrap
	7. sqlite

	Development default directory structure
	index.php
	default.tpl.html -- default template file
	db\* -- everything is dumped on this directory
	db\cache\* -- general purpose temporary directory
	db\pstahl-sqlite.db -- database

	Export default directory structure
	index.html -- home page blog summary
	<pageno>\index.html -- paginated blog summary
	archives\index.html -- blog summary
	archives\<year-month>\index.html -- list of blogs in that year-month
	archives\<year-month>\<segment>-id\index.html
	photo\<uploaddttm>\<IMG_,THB_,WEB_><filename>.JPG

	
	Future Changes
	i. bug: update deploy and template content upon changing database
	ii. improvmement: change the paging from old contents should have the lower integer, this is helpful for google search permlinks
	iii. improvement: add {{blog.topic.next}} and {{blog.topic.previous}} reference to show the next and previous blogs within pages
	iv. improvement: photo full reference with url
	v. enhancement: dead link checker


	1. Batch blog generation. Currently blogs are generated in one go, if the blog post exceed threshold
	  it will throw memory issues.
	2. Archive, versioning section
	3. Preview, can be generated when writing the blog
	4. Next and Previous blog post within the blog view
	. Plugin features, external include of PSTAHL 
	- Google search bar {{google.custom.searchbar}}
	- Facebook comment area {{facebook.comment}}
*/


/**
 *  i. Configuration
 *  ----------------------------------------------------------------------------------------------------
 */
define('LOGIN_USER','{"accounts":[{"alias":"Administrator","email":"admin@email.com","password":"password1"},{"alias":"Power User","email":"poweruser@email.com","password":"password2"}]}');
define('SYSTEM_TITLE','Pstahl v4.1.2');
define('SYSTEM_VERSION','4.1.2');
define('SYSTEM_BASEURL',explode('?', $_SERVER['REQUEST_URI'], 2)[0]);
define('SYSTEM_CONFIG','{"path":[{"alias":"project1","db":"db/project1.db","photo":"db/photocache1/"},{"alias":"project2","db":"db/project2.db","photo":"db/photocache2/"}]}');

/**
 *  ii. Begin instance calls
 *  ----------------------------------------------------------------------------------------------------
 */

$controller = new Controller();

/**
 *  I. Database
 *  ----------------------------------------------------------------------------------------------------
 */
class Database extends SQLite3 {

	private $db = NULL;
	private $isopen = FALSE;	

	/*
	 * i. Initially test for database connection, and perform initialization
	 */
	function __construct( $db ) {
		$this->db = $db;
		$this->opendb();
		$this->busyTimeout(5000);
		$this->closedb();		
	}	

   /*
	* ii. Generate PSTAHL tables
	*/   
   function createTables () {
		$sql_createTables =<<<EOF
			CREATE TABLE IF NOT EXISTS PSTAHL (
			  NAME                 TEXT        PRIMARY KEY    NOT NULL,
			  VALUE                TEXT        NOT NULL,
			  LAST_UPDATED_DTTM    DATETIME    NOT NULL );
			
			INSERT INTO PSTAHL (NAME,VALUE,LAST_UPDATED_DTTM) VALUES ("VERSION","4.1.2",CURRENT_TIMESTAMP);
			INSERT INTO PSTAHL (NAME,VALUE,LAST_UPDATED_DTTM) VALUES ("TEST_EXPORT_PATH","/www/uatdomain/",CURRENT_TIMESTAMP);
			INSERT INTO PSTAHL (NAME,VALUE,LAST_UPDATED_DTTM) VALUES ("TEST_BASE_URL","//localhost/uat/",CURRENT_TIMESTAMP);
			INSERT INTO PSTAHL (NAME,VALUE,LAST_UPDATED_DTTM) VALUES ("PROD_EXPORT_PATH","/www/domain/",CURRENT_TIMESTAMP);
			INSERT INTO PSTAHL (NAME,VALUE,LAST_UPDATED_DTTM) VALUES ("PROD_BASE_URL","//localhost/prod/",CURRENT_TIMESTAMP);


			CREATE TABLE IF NOT EXISTS BLOG (
			  BLOG_ID             CHAR(200)         PRIMARY KEY   NOT NULL,
			  TITLE               TEXT              NOT NULL,
			  SEGMENT             TEXT              NOT NULL,
			  STATUS              CHAR(1)           NOT NULL   DEFAULT 'P',			  
			  CONTENT             TEXT,
			  CONTENT_SUMMARY     TEXT,
			  CONTENT_TYPE        CHAR(1)           NOT NULL   DEFAULT 'B',
			  CONTENT_PATH        TEXT              NULL, 
			  PUBLISH_DTTM        DATETIME,
			  CREATED_DTTM        DATETIME          NOT NULL   DEFAULT CURRENT_TIMESTAMP,
			  LAST_UPDATED_DTTM   DATETIME          NOT NULL );

			CREATE INDEX IF NOT EXISTS BLOG_CREATED_DTTM ON BLOG (CREATED_DTTM);
			CREATE INDEX IF NOT EXISTS BLOG_LAST_UPDATED_DTTM ON BLOG (LAST_UPDATED_DTTM);
			
			CREATE TABLE IF NOT EXISTS TAGS (
			  BLOG_ID        INT          NOT NULL,
			  TAG            CHAR(200)    NOT NULL );

			CREATE INDEX IF NOT EXISTS TAGS_BLOG_ID ON TAGS (BLOG_ID);
			CREATE INDEX IF NOT EXISTS TAGS_TAG ON TAGS (TAG);

			CREATE TABLE IF NOT EXISTS PHOTO (
			  PHOTO_ID     INTEGER      PRIMARY KEY AUTOINCREMENT  NOT NULL,
			  NAME     STRING (200) NOT NULL,
			  DESCRIPTION  STRING (400) NOT NULL  DEFAULT Anonymous,
			  IMAGE        BLOB         NOT NULL,			  
    		  STATUS    CHAR (1)     DEFAULT A NOT NULL,
			  CREATED_DTTM DATETIME     DEFAULT (CURRENT_TIMESTAMP)  NOT NULL
			);

			CREATE INDEX IF NOT EXISTS PHOTO_CREATED_DTTM ON PHOTO (CREATED_DTTM);
			CREATE INDEX IF NOT EXISTS PHOTO_DESCRIPTION ON PHOTO (DESCRIPTION);

EOF;

		$ret = $this->exec($sql_createTables);
	}


	public function opendb() {
		if( $this->isopen !== TRUE) {
			$this->open($this->db);
		}
		$this->isopen = TRUE;
	}

	public function closedb() {
		if( $this->isopen === TRUE) {
			$this->close();
		}
		$this->isopen = FALSE;
	}

	/*
	 * . Build database for first time access, check version.
	 */
	function initialize() {
		$this->opendb();
		$ret = $this->query('SELECT count(name) as count FROM sqlite_master WHERE type="table" AND name in ("PSTAHL","BLOG","TAGS","PHOTO")');
		$row = $ret->fetchArray(SQLITE3_ASSOC);
		if( $row['count'] != 4 ) {
			$this->createTables();
		}
		$this->closedb();
	}	

	/*
	 * . List config map
	 */
	function listPstahl() {
		$this->opendb();
		$sql = 'SELECT * FROM PSTAHL';
		$result = $this->query($sql);
		$arr = array();
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {
			array_push($arr, $row);
		}		
		return $arr;   	
	}

	/*
	 * . Get config map
	 */
	function getPstahl($config) {
		$this->opendb();
		$sql = 'SELECT * FROM PSTAHL WHERE NAME=:NAME';
		$stmt = $this->prepare($sql);
		$stmt->bindValue(':NAME', $config['NAME'], SQLITE3_TEXT);
		$result = $stmt->execute();
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {
			return $row;
		}		
		return FALSE;
	}

	/*
	 * . Save config map
	 */	      
	function savePstahl($config) {
		$this->opendb();
		foreach($config as $key => $value) {    
			$sql = 'REPLACE INTO PSTAHL (NAME,VALUE,LAST_UPDATED_DTTM) VALUES (:NAME,:VALUE,CURRENT_TIMESTAMP)';		
			$stmt = $this->prepare($sql);
			$stmt->bindValue(':NAME', $config['NAME'], SQLITE3_TEXT);
			$stmt->bindValue(':VALUE', $config['VALUE'], SQLITE3_TEXT);
			$result = $stmt->execute();
		}   		
   		return $config;
   }      

	/*
	 * . Create blog entry
	 */
	function createBlog(&$blog) {
		$this->opendb();
		$blog['BLOG_ID'] = hash('md5',$blog['TITLE'] . time());
		$sql = 'INSERT INTO BLOG (BLOG_ID,TITLE,SEGMENT,STATUS,PUBLISH_DTTM,CONTENT,CONTENT_SUMMARY,LAST_UPDATED_DTTM,CONTENT_TYPE,CONTENT_PATH) 
			VALUES (:BLOG_ID,:TITLE,:SEGMENT,:STATUS,:PUBLISH_DTTM,:CONTENT,:CONTENT_SUMMARY,CURRENT_TIMESTAMP,:CONTENT_TYPE,:CONTENT_PATH)';		
		$stmt = $this->prepare($sql);
		$stmt->bindValue(':BLOG_ID', $blog['BLOG_ID'], SQLITE3_TEXT);
		$stmt->bindValue(':TITLE', $blog['TITLE'], SQLITE3_TEXT);
		$stmt->bindValue(':SEGMENT', $blog['SEGMENT'], SQLITE3_TEXT);
		$stmt->bindValue(':STATUS', $blog['STATUS'], SQLITE3_TEXT);
		$stmt->bindValue(':PUBLISH_DTTM', $blog['PUBLISH_DTTM'], SQLITE3_TEXT);
		$stmt->bindValue(':CONTENT', $blog['CONTENT'], SQLITE3_TEXT);
		$stmt->bindValue(':CONTENT_SUMMARY', $blog['CONTENT_SUMMARY'], SQLITE3_TEXT);
		$stmt->bindValue(':CONTENT_TYPE', $blog['CONTENT_TYPE'], SQLITE3_TEXT);
		$stmt->bindValue(':CONTENT_PATH', $blog['CONTENT_PATH'], SQLITE3_TEXT);
		$result = $stmt->execute();				

		$this->saveTags($blog);

		return $blog;
	}

	/*
	 * . Update blog entry
	 */	
	function updateBlog(&$blog) {
		$this->opendb();
		$sql = 'UPDATE BLOG SET TITLE=:TITLE,SEGMENT=:SEGMENT,STATUS=:STATUS,PUBLISH_DTTM=:PUBLISH_DTTM,
			CONTENT=:CONTENT,CONTENT_SUMMARY=:CONTENT_SUMMARY,LAST_UPDATED_DTTM=CURRENT_TIMESTAMP,CONTENT_TYPE=:CONTENT_TYPE,
			CONTENT_PATH=:CONTENT_PATH WHERE BLOG_ID=:BLOG_ID';
		$stmt = $this->prepare($sql);
		$stmt->bindValue(':BLOG_ID', $blog['BLOG_ID'], SQLITE3_TEXT);
		$stmt->bindValue(':TITLE', $blog['TITLE'], SQLITE3_TEXT);
		$stmt->bindValue(':SEGMENT', $blog['SEGMENT'], SQLITE3_TEXT);
		$stmt->bindValue(':STATUS', $blog['STATUS'], SQLITE3_TEXT);
		$stmt->bindValue(':PUBLISH_DTTM', $blog['PUBLISH_DTTM'], SQLITE3_TEXT);
		$stmt->bindValue(':CONTENT', $blog['CONTENT'], SQLITE3_TEXT);	
		$stmt->bindValue(':CONTENT_SUMMARY', $blog['CONTENT_SUMMARY'], SQLITE3_TEXT);	
		$stmt->bindValue(':CONTENT_TYPE', $blog['CONTENT_TYPE'], SQLITE3_TEXT);
		$stmt->bindValue(':CONTENT_PATH', $blog['CONTENT_PATH'], SQLITE3_TEXT);
		$result = $stmt->execute();		

		$this->saveTags($blog);

		return $blog;
	}

	/*
	 * . List all the blogs
	 */	
	function listBlog($blog) {		
		$this->opendb();
		$sql = 'SELECT BLOG_ID AS ID,TITLE,SEGMENT,PUBLISH_DTTM,STATUS,CONTENT_PATH FROM BLOG WHERE STATUS!="R" AND CONTENT_TYPE=:CONTENT_TYPE ORDER BY DATETIME(PUBLISH_DTTM) DESC';
		$stmt = $this->prepare($sql);
		$stmt->bindValue(':CONTENT_TYPE', $blog['CONTENT_TYPE'], SQLITE3_TEXT);
		$result = $stmt->execute();		

		$arr = array();
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {
			array_push($arr, $row);
		}
		return $arr;
	}

	/*
	* . Retreive target blog
	*/
	function getBlog(&$blog) {
			$this->opendb();
		$sql = 'SELECT BLOG_ID,TITLE,SEGMENT,PUBLISH_DTTM,STATUS,CONTENT,CONTENT_SUMMARY,CONTENT_TYPE,CONTENT_PATH FROM BLOG WHERE BLOG_ID=:BLOG_ID';
		$stmt = $this->prepare($sql);
		$stmt->bindValue(':BLOG_ID', $blog['BLOG_ID'], SQLITE3_TEXT);
		$result = $stmt->execute();		
		$blog = $result->fetchArray(SQLITE3_ASSOC);
		$blog['TAGS'] = implode(',',$this->listTags($blog));

		return $blog;
	}

   /*
	* . Save tag entries
	*/	
	function saveTags(&$blog) {
			$this->opendb();
			$sql = 'DELETE FROM TAGS WHERE BLOG_ID=:BLOG_ID';
			$stmt = $this->prepare($sql);
		$stmt->bindValue(':BLOG_ID', $blog['BLOG_ID'], SQLITE3_TEXT);
		$result = $stmt->execute();	

		$tags = explode(',',$blog['TAGS']);

		foreach($tags as $tag) {    
			if(trim($tag) != '') {
				$sql = 'INSERT INTO TAGS (BLOG_ID,TAG) VALUES (:BLOG_ID,:TAG)';		
				$stmt = $this->prepare($sql);
				$stmt->bindValue(':BLOG_ID', $blog['BLOG_ID'], SQLITE3_TEXT);
				$stmt->bindValue(':TAG', $tag, SQLITE3_TEXT);
				$result = $stmt->execute();				    	
			}		
		}
			
	}

   /*
    * . List all the tags of a given blog
    */	   
	function listTags($blog) {
		$this->opendb();
		$sql = 'SELECT TAG FROM TAGS WHERE BLOG_ID=:BLOG_ID';
		$stmt = $this->prepare($sql);
		$stmt->bindValue(':BLOG_ID', $blog['BLOG_ID'], SQLITE3_TEXT);
		$result = $stmt->execute();	
		$arr = array();
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {
			array_push($arr, $row['TAG']);
		}  	
		return $arr;
	}


	function listPhoto() {
		$this->opendb();
		$sql = 'SELECT PHOTO_ID, NAME, DESCRIPTION, CREATED_DTTM FROM PHOTO WHERE STATUS="A" ORDER BY CREATED_DTTM DESC';
		$stmt = $this->prepare($sql);
		$result = $stmt->execute();	
		$arr = array();
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {
			array_push($arr, $row);
		}  	
		return $arr;
	}

	function listAllPhoto() {
		$this->opendb();
		$sql = 'SELECT PHOTO_ID, NAME, DESCRIPTION, IMAGE, CREATED_DTTM FROM PHOTO WHERE STATUS="A" ORDER BY CREATED_DTTM DESC';
		$stmt = $this->prepare($sql);
		$result = $stmt->execute();	
		$arr = array();
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {
			array_push($arr, $row);
		}  	
		return $arr;
	}

	function getPhoto($photo) {
		$this->opendb();
		$sql = 'SELECT PHOTO_ID, NAME, DESCRIPTION, IMAGE, CREATED_DTTM FROM PHOTO WHERE STATUS="A" AND PHOTO_ID=:PHOTO_ID ORDER BY CREATED_DTTM DESC';
		$stmt = $this->prepare($sql);
		$stmt->bindValue(':PHOTO_ID', $photo['PHOTO_ID'], SQLITE3_TEXT);
		$result = $stmt->execute();	
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {
			return $row;
		}  	
		return FALSE;
	}

	function createPhoto($photo) {
		$this->opendb();		

		$sql = 'INSERT INTO PHOTO (NAME,DESCRIPTION,IMAGE) VALUES (:NAME,:DESCRIPTION,:IMAGE)';		
		$stmt = $this->prepare($sql);
		$stmt->bindValue(':NAME', $photo['NAME'], SQLITE3_TEXT);
		$stmt->bindValue(':DESCRIPTION', $photo['DESCRIPTION'], SQLITE3_TEXT);
		$stmt->bindValue(':IMAGE', $photo['IMAGE'], SQLITE3_BLOB);	
		$result = $stmt->execute();				
		
		return $photo;
	}

	function updatePhoto($photo) {
		$this->opendb();		

		$sql = 'UPDATE PHOTO SET NAME=:NAME,DESCRIPTION=:DESCRIPTION,IMAGE=:IMAGE WHERE PHOTO_ID = :PHOTO_ID';		
		$stmt = $this->prepare($sql);
		$stmt->bindValue(':NAME', $photo['NAME'], SQLITE3_TEXT);
		$stmt->bindValue(':DESCRIPTION', $photo['DESCRIPTION'], SQLITE3_TEXT);
		$stmt->bindValue(':IMAGE', $photo['IMAGE'], SQLITE3_BLOB);	
		$stmt->bindValue(':PHOTO_ID', $photo['PHOTO_ID'], SQLITE3_INTEGER);	
		$result = $stmt->execute();				
		
		return $photo;
	}

}


/**
 *  Classname: Controller
 *  Handles page controller
 *  
 */
class Controller {
	
	protected $config;	

	protected $db;
	protected $view;
	protected $helper;	
	protected $validator;

	private $gett;
	private $post;

	/**
	 *	Controller Class Contructor
	 *  - 
	 */
	function __construct() {	
				
		session_start();

		$this->gett = $_GET;
		$this->post = $_POST;

		$this->helper = new Helper( $this->gett, $this->post );
		$this->validator = new Validator( $this->gett, $this->post );				
		$this->view = new View( $this->helper );

		$this->checkSignout();

		$this->checkRedirect();			
		
		if( isset($_SESSION["user_session"]) && isset($this->post['action']) ) {				
			$this->actionProcess();						
		}
		else if( isset($_SESSION["user_session"]) && isset($this->gett['photo']) ) {				
			$this->actionPhoto();									
		}
		else if ( isset($_SESSION["user_session"]) ) {
			$this->adminPage();
		}
		else { // no session
			$this->loginPage();
		}
	}	

	/**
	 *	Method:
	 *
	 */
	protected function checkSignout() {
		if( isset($_SESSION["user_session"]) && isset($this->gett['signout']) )  {
			session_destroy();
			$this->view->loginView();
		}	
	}

	/**
	 *	Method:
	 *
	 */
	protected function checkRedirect() {
		if( isset($_SESSION["view_redirect"]) ) {
			$viewRedirect = $_SESSION["view_redirect"];
			unset( $_SESSION["view_redirect"] ); 
			$this->view->redirect( $viewRedirect );
			die();
		}
	}

	/**
	 *	Method:
	 *
	 */
	protected function checkConfig() {
		$config = NULL;
		if( isset($_SESSION["user_config"]) ) {
			$config = $_SESSION["user_config"];
		}
		else {
			$configAll = json_decode(SYSTEM_CONFIG);
			$config = $configAll->path[0];
			$_SESSION["user_config"] = $config;
		}
		$this->db = new Database( $config->db );
	}
	
	/**
	 *	Method:
	 *
	 */
	protected function selectDatabase() {
		$configAll = json_decode(SYSTEM_CONFIG);
		$config = $configAll->path[ $this->helper->gp('db') ];
		$_SESSION["user_config"] = $config;		
		$this->db = new Database( $config->db );
		$this->adminPage();
	}

	/**
	 *	Method:
	 *
	 */
	protected function actionProcess() {
		
		$this->checkConfig();

		switch ($this->post['action'] ) {
			case 'database.select': $this->selectDatabase(); break;
			case 'blog.list': $this->listBlogData(); break;
			case 'blog.create.get': $this->getBlogData(); break;
			case 'blog.create.save': $this->saveBlogView(); break;
			case 'pages.list': $this->listPagesData(); break;			
			case 'pages.create.get': $this->getPagesData(); break;
			case 'pages.create.save': $this->savePagesView(); break;
			case 'photo.list': $this->listPhotoData(); break;		
			case 'photo.upload': $this->uploadPhotoView(); break;
			case 'photo.reupload': $this->reuploadPhotoView(); break;
			case 'templates.get': $this->getTemplatesData(); break;								
			case 'templates.create.save': $this->saveTemplatesView(); break;
			case 'deploy.get': $this->getExportConfigData(); break;
			case 'deploy.create.save': $this->saveExportConfigView(); break;
			case 'deploy.create.preview': $this->exportPreview(); break;
			case 'deploy.create.production': $this->exportProduction(); break;
			case 'deploy.create.production.photo': $this->exportProductionPhoto(); break;
			default: break;
		}
	}

	/**
	 *	Method:
	 *
	 */
	protected function actionPhoto() {	
		$this->checkConfig();
		$service = new PhotoService($this->db, $this->post, $this->gett, $this->validator, $this->helper);	
		$photo = $service->getPhoto();
		$this->view->photoView( $photo['IMAGE'] );		
	}

	/**
	 *	Method: loginPage
	 *  
	 */
	protected function loginPage() {
		$service = new LoginService($this->db, $this->post, $this->validator, $this->helper);
		if( $service->hasRequired() ) {
			if( $service->checkUsernamePassword() ) {
				$_SESSION["user_session"] = $service->user; // set user session
				$this->adminPage();		// redirect to home admin page				
			}
			else {
				$this->view->loginView( array('error'=>TRUE) ); // display login page, invalid username and password				
			}
		}
		else {
			$this->view->loginView(); // display login page	
		}
	}

	/**
	 *	Method: adminPage
	 *  
	 */
	protected function adminPage() {
		$this->checkConfig();
		$this->db->initialize();
		$this->view->adminView();
	}

	/**
	 *	Method: getBlogPage
	 *  
	 */
	protected function getBlogData() {
		$service = new BlogService($this->db, $this->post, $this->validator, $this->helper);
		if( $service->hasAction() && $this->helper->cp('id') ) {
			$service->getBlog();
			$this->view->json( $this->post );
		}		
	}


	/**
	 *	Method: listBlogData
	 *  
	 */
	protected function listBlogData() {
		$service = new BlogService($this->db, $this->post, $this->validator, $this->helper);	
		$data = array();
		$data['table'] = $service->listBlog();
		$this->view->json( $data );
	}


	/**
	 *	Method: saveBlogData
	 *  
	 */
	protected function saveBlogView() {
		$service = new BlogService($this->db, $this->post, $this->validator, $this->helper);	

		if( $service->hasAction() && $service->hasRequired()) {
			if( $this->helper->cp('id') ) {
				$service->updateBlog();		
				$this->post['state'] = 'success.update';										
			}
			else {
				$service->createBlog();	
				$this->post['state'] = 'success.create';
			}
						
			$this->view->jsont( $this->post );												
		}
		else {
			$this->view->jsont();	
		}
	}
	
	/**
	 *	Method: getPagesData
	 *  
	 */
	protected function getPagesData() {
		$service = new PagesService($this->db, $this->post, $this->validator, $this->helper);
		if( $service->hasAction() && $this->helper->cp('id') ) {
			$service->getBlog();
			$this->view->json( $this->post );
		}		
	}

	/**
	 *	Method: listPagesData
	 *  
	 */
	protected function listPagesData() {
		$service = new PagesService($this->db, $this->post, $this->validator, $this->helper);	
		$data = array();
		$data['table'] = $service->listPages();
		$this->view->json( $data );
	}	

	/**
	 *	Method: savePagesView
	 *  
	 */
	protected function savePagesView() { 
		$service = new PagesService($this->db, $this->post, $this->validator, $this->helper);
		if( $service->hasAction() && $service->hasRequired()) { 	
			if( $this->helper->cp('id') ) { 
				$service->updatePages();		
				$this->post['state'] = 'success.update';										
			}
			else {
				$service->createPages();	
				$this->post['state'] = 'success.create';
			}						
			$this->view->jsont( $this->post );												
		}
		else {
			$this->view->jsont();	
		}
	}

	/**
	 *	Method: listPhotoData
	 *  
	 */
	protected function listPhotoData() {
		$service = new PhotoService($this->db, $this->post, $this->gett, $this->validator, $this->helper);	
		$data = array();
		$data['table'] = $service->listPhoto();
		$this->view->json( $data );
	}

	/**
	 *	Method: uploadPhotoView
	 *  
	 */
	protected function uploadPhotoView() {
		$service = new PhotoService($this->db, $this->post, $this->gett, $this->validator, $this->helper);
		for($i=0;$i<count($_FILES['files']['name']);$i++) {
			$service->uploadPhoto($_FILES['files']['name'][$i], $_FILES['files']['tmp_name'][$i]);	
		}
		$this->view->jsont();
	}

	/**
	 *	Method: reuploadPhotoView
	 *  
	 */
	protected function reuploadPhotoView() {
		$service = new PhotoService($this->db, $this->post, $this->gett, $this->validator, $this->helper);
		for($i=0;$i<count($_FILES['files']['name']);$i++) {
			$service->uploadPhoto($_FILES['files']['name'][$i], $_FILES['files']['tmp_name'][$i], $this->post['photoid']);				
		}
		$this->view->jsont();
	}

	/**
	 *	Method: getTemplatesData
	 *  
	 */
	protected function getTemplatesData() {
		$service = new TemplatesService($this->db, $this->post, $this->validator, $this->helper);
		if( $service->hasAction() ) { 
			$service->getTemplates();
			$this->view->json( $this->post );
		}		
	}

	/**
	 *	Method: saveTemplatesView
	 *  
	 */
	protected function saveTemplatesView() {		
		$service = new TemplatesService($this->db, $this->post, $this->validator, $this->helper);
		if( $service->hasAction() ) {
			$service->updateTemplates();						
			$this->view->jsont( $this->post );												
		}
		else {
			$this->view->jsont();	
		}
	}

	/**
	 *	Method: getExportConfigData
	 *  
	 */
	protected function getExportConfigData() {
		$service = new DeployService($this->db, $this->post, $this->validator, $this->helper);
		if( $service->hasAction() ) { 
			$service->getExportConfig();
			$this->view->json( $this->post );
		}		
	}

	/**
	 *	Method: saveExportConfigCommon
	 *	Method: saveExportConfigView
	 *  
	 */
	private function saveExportConfigCommon(&$service = NULL) {
		$service = new DeployService($this->db, $this->post, $this->validator, $this->helper);
		if( $service->hasAction() ) {
			$service->updateExportConfig();	
			return TRUE;								
		}
		return FALSE;
	}
	protected function saveExportConfigView() {		
		if( $this->saveExportConfigCommon() ) {
			$this->view->jsont( $this->post );	
		}		
		$this->view->jsont();		
	}

	

	/**
	 *	Method: exportPreview
	 *  
	 */
	protected function exportPreview() {	
		$service = NULL;	
		if( $this->saveExportConfigCommon($service) ) {			
			$service->executePreviewExport();
		}
	}
	
	/**
	 *	Method: exportProduction
	 *  
	 */
	protected function exportProduction() {		
		$service = NULL;	
		if( $this->saveExportConfigCommon($service) ) {
			$service->executeProductionExport();
		}
	}

	protected function exportProductionPhoto() {
		if( $this->saveExportConfigCommon() ) {
			$photoService = new PhotoService($this->db, $this->post, $this->gett, $this->validator, $this->helper);			
			$photoService->export( $this->post['prod-path'] );			
		}		
		$this->view->jsont();
	}

}

/**
 *  Classname: Helper
 *  Common utility function for pstahl application.
 */
class Helper {

	private $gett;
	private $post;

	function __construct(&$gett, &$post) {		
		$this->gett = &$gett;
		$this->post = &$post;
	}

	/**
	 *	Method: removeExtraSpaces
	 *	Parameter: String, free text
	 *	Return: String
	 *	- Clears extra spaces, breaks and carriage returns. 
	 *	- Convert double space to single space.
	 *	- Remove space at start and end tag
	 *  - Prevent extra space clearing for anchor and span tags
	 */	
	function removeExtraSpaces( $str="" ) {
		$s = preg_replace(array('/\s{2,}/','/[\t\n]/'),' ',$str);
		$s = $this->spaceTildeSwap($s, array(' <a',' <span',' <b',' <strong',' <i','a> ','span> ','b> ','strong> ','i> '));
		$s = str_replace('> ','>',$s); $s = str_replace(' <','<',$s);
		$s = $this->tildeSpaceSwap($s, array('~<a','~<span','~<b','~<strong','~<i','a>~','span>~','b>~','strong>~','i>~'));
		return $s;
	}

	function spaceTildeSwap($s, $ar) {
		for($i=0;$i<count($ar);$i++) {
			$s = str_replace($ar[$i], str_replace(' ','~',$ar[$i]) ,$s);
		}
		return $s;
	}

	function tildeSpaceSwap($s, $ar) {
		for($i=0;$i<count($ar);$i++) {
			$s = str_replace($ar[$i], str_replace('~',' ',$ar[$i]) ,$s);
		}
		return $s;
	}

	/**
	 *	Method: cleanPath
	 *	Parameter: String, url or filepath string
	 *	Return: String	 
	 *  Ensures url or filepath uses slash instead of backslash and it has a slash suffix.
	 */	
	function cleanPath( $path ) {
		return rtrim(str_replace('\\', '/', $path),'/') . '/';
	}

	/**
	 *	Method: getStringBetween
	 *  Get the string between two characters
	 */
	function getStringBetween($string, $start, $end){
		$string = ' ' . $string;
		$ini = strpos($string, $start);
		if ($ini == 0) return '';
		$ini += strlen($start);
		$len = strpos($string, $end, $ini) - $ini;
		return substr($string, $ini, $len);
	}

	/**
	 *	Method: cleanPath
	 *  Remove url prefix slash and append 
	 */		
	function baseUrlPath( $url = "" ) { 
		return (trim($url) === "") ? $this->cleanPath(SYSTEM_BASEURL) : $this->cleanPath(SYSTEM_BASEURL) . $this->cleanPath(ltrim($url,'/'));
	}

	function cp( $key, $compareto = "" ) {
		return isset( $this->post[$key] ) && trim($this->post[$key])!=$compareto ;
	}

	function cg( $key, $compareto = "" ) {
		return isset( $this->gett[$key] ) && trim($this->gett[$key])!=$compareto ;
	}

	function gp( $key, $compareto = "", $iffalse = "" ) {
		return $this->cp($key,$compareto) ? $this->post[$key] : $iffalse ;
	}

	function gg( $key, $compareto = "", $iffalse = "" ) {
		return $this->cg($key,$compareto) ? $this->gett[$key] : $iffalse ;
	}
}


/**
 *  Classname: Validator
 *  
 */
class Validator {

	private $gett;
	private $post;

	function __construct(&$gett, &$post) {		
		$this->gett = &$gett; $this->post = &$post;
	}

	public function hasAction() {
		if( isset($this->post['action']) ) {
			return TRUE;
		}	
		return FALSE;
	}

	public function hasRequired($arrayPostName) {
		foreach ($arrayPostName as $key => $value) {
			if( !isset( $this->post[$value] ) || trim($this->post[$value])=="" ) { return FALSE; }
		}
		return TRUE;
	}
}


/**
 *  Classname: BaseService
 *  
 */

class BaseService {

	protected $db;
	protected $gett;
	protected $post;	
	protected $validator;
	protected $helper;

	function __construct(&$db, &$post, &$validator, &$helper) {
		$this->db = &$db;
		$this->post = &$post;
		$this->validator = &$validator;
		$this->helper = &$helper;		
	}

	public function hasAction() {
		return $this->validator->hasAction();
	}

	public function hasRequired($ar = array()) {
		if ( $this->validator->hasRequired($ar) ) {
			return TRUE;
		}
		return FALSE;
	}
}

/**
 *  Classname: BlogService
 *  
 */
class BlogService extends BaseService {

	protected $blog;

	function __construct(&$db, &$post, &$validator, &$helper) {
		parent::__construct($db, $post, $validator, $helper);
	}

	protected function prepareModel() {
		$this->blog = array();
		$this->blog['BLOG_ID'] = $this->helper->gp('id');
		$this->blog['TITLE'] = $this->helper->gp('title');
		$this->blog['TAGS'] = $this->helper->gp('tags');
		$this->blog['STATUS'] = $this->helper->gp('status'); 
		$this->blog['PUBLISH_DTTM'] = $this->helper->gp('publishdate');
		$this->blog['CONTENT'] = $this->helper->gp('content');		
		$this->blog['CONTENT_SUMMARY'] = $this->helper->gp('preview');
		$this->blog['CONTENT_TYPE'] = 'B';
		$this->blog['CONTENT_PATH'] = 'archive/';
		$this->blog['SEGMENT']=strtolower( preg_replace("/[^\w]+/", "-", $this->helper->gp('title') ) );	
	}

	protected function preparePostback() {
		$this->post['id'] = $this->blog['BLOG_ID'];
		$this->post['title'] = $this->blog['TITLE'];
		$this->post['tags'] = $this->blog['TAGS'];
		$this->post['status'] = $this->blog['STATUS'];
		$this->post['publishdate'] = $this->blog['PUBLISH_DTTM'];
		$this->post['content'] = $this->blog['CONTENT'];
		$this->post['contentpath'] = $this->blog['CONTENT_PATH'];
		$this->post['preview'] = $this->blog['CONTENT_SUMMARY'];
	}

	public function hasRequired($ar = array()) {
		return parent::hasRequired( array('title','tags','publishdate','status','preview','content') );		
	}

	public function listBlog() {
		$this->prepareModel();		
		return $this->db->listBlog($this->blog);
	}

	public function getBlog() {
		$this->prepareModel();
		$this->blog = $this->db->getBlog($this->blog);
		$this->preparePostback();
	}

	public function createBlog() {
		$this->prepareModel();
		$this->db->createBlog($this->blog);
		$this->preparePostback();
	}

	public function updateBlog() {
		$this->prepareModel();
		$this->db->updateBlog($this->blog); 
		$this->preparePostback();		
	}
}


/**
 *  Classname: PageService
 *  
 */
class PagesService extends BlogService {

	function __construct(&$db, &$post, &$validator, &$helper) {
		parent::__construct($db, $post, $validator, $helper);
	}

	protected function prepareModel() {
		parent::prepareModel();
		$this->blog['CONTENT_TYPE'] = 'P';
		$this->blog['CONTENT_PATH'] = $this->helper->gp('contentpath'); 
	}

	public function hasRequired() {
		return BaseService::hasRequired( array('title','tags','publishdate','status','content') );
	}

	public function listPages() {
		$this->prepareModel();		
		return $this->db->listBlog($this->blog);
	}

	public function createPages() {
		$this->prepareModel();		
		$this->db->createBlog($this->blog);
		$this->preparePostback();
	}

	public function updatePages() {
		$this->prepareModel();
		$this->db->updateBlog($this->blog); 
		$this->preparePostback();	
	}

}

/**
 *  Classname: PhotoService
 *  
 */
class PhotoService extends BaseService {

	protected $photo;	
	protected $cacheDir;
	protected $uploadDir;

	function __construct(&$db, &$post, &$gett, &$validator, &$helper) {
		parent::__construct($db, $post, $validator, $helper);
		$this->gett = $gett;
		$this->cacheDir = $this->helper->cleanPath($_SESSION["user_config"]->photo);
		$this->uploadDir = $this->helper->cleanPath( $this->cacheDir . 'upload/');

		$fileDirService = new FileDirectoryService();
		$fileDirService->createDir( $this->cacheDir );
		$fileDirService->createDir( $this->uploadDir );
	}

	public function listPhoto() {
		return $this->db->listPhoto();
	}

	public function getPhoto( $photoId=NULL ) {
		$ar = array('PHOTO_ID'=>$photoId);
		if( $photoId === NULL ) {
			$ar = array('PHOTO_ID'=>$this->helper->gg('photo'));
		}		
		$this->photo = $this->db->getPhoto($ar); //echo 1; die();
		$this->generateCachePhoto();
		$this->getPhotoCached();	
		return $this->photo;
	}

	public function replacePhotoCode($content) {
		$regex = '/{{photo:(.*?)}}/';
		preg_match_all($regex, $content, $match); 
		if( count($match) > 1 ) {		
			for($i=0;$i<count($match[0]);$i++) {		
				$pid = $this->helper->getStringBetween($match[0][$i],'{{photo:','}}');		
				$ar = array('PHOTO_ID'=>$pid);
				$this->photo = $this->db->getPhoto($ar); 
				$this->generateCachePhoto();				
				$content = str_replace($match[0][$i], $this->photo['NAME'], $content);			
			}
		}		
		return $content;	
	}

	private function getPhotoCached() {
		$path = $this->cacheDir . $this->photo['PHOTO_ID'];
		$photoSuffix = '_ORI';
		$fileDirService = new FileDirectoryService();
		switch( $this->helper->gg('size') ) {
			case 'thumb': $photoSuffix = '_THB';  break;
			case 'norm': $photoSuffix = '_NOR';  break;
			case 'orig': $photoSuffix = '_ORI';  break;
			default: break;
		}
		$this->photo['IMAGE'] = $fileDirService->readFile($path . $photoSuffix);		
	}

	public function generateCachePhoto($forceGenerate=FALSE) {	
		$fileDirService = new FileDirectoryService();
		$pathOriginal = $this->cacheDir . $this->photo['PHOTO_ID'] . '_ORI';
		$pathNormal = $this->cacheDir . $this->photo['PHOTO_ID'] . '_NOR';
		$pathThumb = $this->cacheDir . $this->photo['PHOTO_ID'] . '_THB';		
		if( $fileDirService->checkFiles(array($pathOriginal, $pathThumb)) == FALSE || $forceGenerate ) {
			$photoConvService = new PhotoConverterService();				
			$fileDirService->writeFile($pathOriginal , $this->photo['IMAGE'] );
			$photoConvService->load($pathOriginal);
			$photoConvService->resizeToWidth(640);
			$photoConvService->save($pathNormal);
			$photoConvService->resizeToWidth(256);
			$photoConvService->save($pathThumb);
		}

	}

	public function uploadPhoto($name, $file, $photoId=NULL) {				
		$filename = $this->uploadDir . date("YmdHis") . '_' . $name;
		if( move_uploaded_file($file, $filename) ) {
			$fileDirService = new FileDirectoryService();
			$photo = array();			
			$photo['DESCRIPTION']='Initial image upload with an orignal name of [' . basename($name) . '] and an upload date of ['.date("Y-m-d h:i:sa").'].';
			$photo['IMAGE'] = $fileDirService->readFile($filename);
			$photo['NAME']=$fileDirService->generateImageName($filename,$photo['IMAGE']);
			if( $photoid===NULL ) {
				$this->db->createPhoto($photo);
			}
			else {
				$photo['PHOTO_ID'] = $photoId;
				$this->photo['PHOTO_ID'] = $photo['PHOTO_ID'];
				$this->photo['IMAGE'] = $photo['IMAGE'];
				$this->db->updatePhoto($photo);	
				$this->generateCachePhoto(TRUE);
			}
			
		}
	}

	public function export($basepathExport) {
		$exportpath = $basepathExport . 'photo/';

		if ( !is_dir($exportpath) ) { mkdir($exportpath, 0700, true); }

		$arr = $this->db->listPhoto();
		for($i=0; $i<count($arr); $i++ ) {
			if( !file_exists($exportpath . $arr[$i]['NAME']) ) {	
				if( !file_exists($this->cacheDir.$arr[$i]['PHOTO_ID'].'_ORI') ) {					
					$this->getPhoto( $arr[$i]['PHOTO_ID'] );
				}
				copy( $this->cacheDir.$arr[$i]['PHOTO_ID'].'_ORI', $exportpath.$arr[$i]['NAME'] );
				copy( $this->cacheDir.$arr[$i]['PHOTO_ID'].'_THB', $exportpath.'THB_' . $arr[$i]['NAME'] );
				copy( $this->cacheDir.$arr[$i]['PHOTO_ID'].'_NOR', $exportpath.'NOR_' . $arr[$i]['NAME'] );
			}
		}
	}
}

/**
 *  Classname: FileDirectoryService
 *  
 */
class FileDirectoryService {

	public function createDir($dir) {
		if ( !is_dir($dir) ) { 
			mkdir($dir, 0700, true); 
		}
	}

	public function writeFile($filename,$content) {
		$file = fopen($filename, "w") or die("Unable to open file!");	
		fwrite($file, $content);
		fclose($file);
	}

	public function checkFiles($filenames = array()) {
		if( gettype($filenames)=='array' ) {
			foreach($filenames as $key => $name) { 
				if(!file_exists($name)) { return FALSE; }
			}
		}
		else if( gettype($filenames)=='string' ) {
			return file_exists($filenames);
		}
		return TRUE;
	}

	public function readFile($filename) {
		$read = '';
		if( file_exists($filename) ) {
			$file = fopen($filename, "r") or die("Unable to open file!");			
			while(!feof($file)) { // Output one line until end-of-file
				 $read = $read . fgets($file) ;
			}
			fclose($file);	
		}		
		return $read;
	}

	public function generateImageName($filename,&$image) {
		$photo_info = getimagesizefromstring($image);
		if( $photo_info[2] == IMAGETYPE_JPEG ) {
			return strtoupper(uniqid()) . '_' . filemtime($filename) . '.JPG';
		} elseif( $photo_info[2] == IMAGETYPE_GIF ) {
			return strtoupper(uniqid()) . '_' . filemtime($filename) . '.GIF';
		} elseif( $photo_info[2] == IMAGETYPE_PNG ) {
			return strtoupper(uniqid()) . '_' . filemtime($filename) . '.PNG';
		}				
	}

}

/**
 *  Classname: PhotoConverterService
 *  
 */
class PhotoConverterService {

	var $photo;
	var $photoType;
	var $photoext;

	function load($filename) {

		$photo_info = getimagesize($filename);
		$this->photoType = $photo_info[2];

		if( $this->photoType == IMAGETYPE_JPEG ) {
			$this->photo = imagecreatefromjpeg($filename); $this->photoext = 'JPG';
		} elseif( $this->photoType == IMAGETYPE_GIF ) {
			$this->photo = imagecreatefromgif($filename); $this->photoext = 'GIF';
		} elseif( $this->photoType == IMAGETYPE_PNG ) {
			$this->photo = imagecreatefrompng($filename); $this->photoext = 'PNG';
		}
	}

	function save($filename, $photoType=IMAGETYPE_JPEG, $compression=75, $permissions=null) {

		$photoType = isset($this->photoType) && $this->photoType!=NULL ? $this->photoType : $photoType;

		if( $photoType == IMAGETYPE_JPEG ) {
			imagejpeg($this->photo,$filename,$compression); 
		} elseif( $photoType == IMAGETYPE_GIF ) {
			imagegif($this->photo,$filename);
		} elseif( $photoType == IMAGETYPE_PNG ) {
			imagepng($this->photo,$filename,9);
		}

		if( $permissions != null) {
			chmod($filename,$permissions);
		}
	}

	function output($photoType=IMAGETYPE_JPEG) {
		if( $photoType == IMAGETYPE_JPEG ) {
			imagejpeg($this->photo);
		} elseif( $photoType == IMAGETYPE_GIF ) {
			imagegif($this->photo);
		} elseif( $photoType == IMAGETYPE_PNG ) {
			imagepng($this->photo);
		}
	}

	function getWidth() {
		return imagesx($this->photo);
	}

	function getHeight() {
		return imagesy($this->photo);
	}

	function resizeToHeight($height) {
		$ratio = $height / $this->getHeight();
		$width = $this->getWidth() * $ratio;
		$this->resize($width,$height);
	}

	function resizeToWidth($width) {
		$ratio = $width / $this->getWidth();
		$height = $this->getheight() * $ratio;
		$this->resize($width,$height);
	}

	function scale($scale) {
		$width = $this->getWidth() * $scale/100;
		$height = $this->getheight() * $scale/100;
		$this->resize($width,$height);
	}

	function resize($width,$height) {
		$newImage = imagecreatetruecolor($width, $height);
		if($this->photoType == IMAGETYPE_PNG || $this->photoType == IMAGETYPE_GIF){
			imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
			imagealphablending($newImage, false);
			imagesavealpha($newImage, true);
		}
		imagecopyresampled($newImage, $this->photo, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
		$this->photo = $newImage;
	}
	
}

/**
 *  Classname: TemplatesService
 *  
 */
class TemplatesService extends BaseService {
	function __construct(&$db, &$post, &$validator, &$helper) {
		parent::__construct($db, $post, $validator, $helper);
	}

	public function getTemplates() {
		$result = $this->db->getPstahl(array('NAME'=>'PAGE_TEMPLATE'));
		if($result) {
			$this->post['PAGE_TEMPLATE'] = $result['VALUE'];			
		}
		$result = $this->db->getPstahl(array('NAME'=>'JS_COMMON'));
		if($result) {
			$this->post['JS_COMMON'] = $result['VALUE'];
		}
		$result = $this->db->getPstahl(array('NAME'=>'CSS_COMMON'));
		if($result) {
			$this->post['CSS_COMMON'] = $result['VALUE'];
		}
	}

	public function updateTemplates() {		
		$this->db->savePstahl(array('NAME'=>'PAGE_TEMPLATE','VALUE'=>$this->post['page-template-content-text']));
		$this->db->savePstahl(array('NAME'=>'JS_COMMON','VALUE'=>$this->post['javascript-common-content-text']));
		$this->db->savePstahl(array('NAME'=>'CSS_COMMON','VALUE'=>$this->post['css-common-content-text']));
	}


}

/**
 *  Classname: DeployService
 *  
 */
class DeployService extends BaseService {
	function __construct(&$db, &$post, &$validator, &$helper) {
		parent::__construct($db, $post, $validator, $helper);
	}

	public function getExportConfig() {
		$result = $this->db->getPstahl(array('NAME'=>'PROD_EXPORT_PATH'));
		if($result) {
			$this->post['PROD_EXPORT_PATH'] = $result['VALUE'];			
		}
		$result = $this->db->getPstahl(array('NAME'=>'PROD_BASE_URL'));
		if($result) {
			$this->post['PROD_BASE_URL'] = $result['VALUE'];			
		}
		$result = $this->db->getPstahl(array('NAME'=>'TEST_EXPORT_PATH'));
		if($result) {
			$this->post['TEST_EXPORT_PATH'] = $result['VALUE'];			
		}
		$result = $this->db->getPstahl(array('NAME'=>'TEST_BASE_URL'));
		if($result) {
			$this->post['TEST_BASE_URL'] = $result['VALUE'];			
		}
		$result = $this->db->getPstahl(array('NAME'=>'EXPORT_RUNNING'));
		if($result) {
			$this->post['EXPORT_RUNNING'] = $result['VALUE'];			
		}
		
	}

	public function updateExportConfig() {
		$this->db->savePstahl(array('NAME'=>'PROD_EXPORT_PATH','VALUE'=>$this->post['prod-path']));
		$this->db->savePstahl(array('NAME'=>'PROD_BASE_URL','VALUE'=>$this->post['prod-url']));
		$this->db->savePstahl(array('NAME'=>'TEST_EXPORT_PATH','VALUE'=>$this->post['preview-path']));
		$this->db->savePstahl(array('NAME'=>'TEST_BASE_URL','VALUE'=>$this->post['preview-url']));
	}

	public function executePreviewExport(){
		$ar = array('BASEPATH'=>$this->helper->cleanPath($this->post['preview-path']), 'BASEURL'=>$this->helper->cleanPath($this->post['preview-url']) );
		$this->executeExport($ar);
	}

	public function executeProductionExport(){
		$ar = array('BASEPATH'=>$this->helper->cleanPath($this->post['prod-path']), 'BASEURL'=>$this->helper->cleanPath($this->post['prod-url']) );
		$this->executeExport($ar);
	}

	public function executeExport($config = array()) {
		$result = $this->db->getPstahl(array('NAME'=>'EXPORT_RUNNING'));

		if($result==FALSE || $result['VALUE']=='N' || $result['VALUE']=='Y') {
			
			$photoService = new PhotoService($this->db, $this->post, $this->gett, $this->validator, $this->helper);

			// Update database to lock export service
			$this->db->savePstahl(array('NAME'=>'EXPORT_RUNNING','VALUE'=>'Y'));

			// Run the process in the background. recommended that it is shot at an ajax request. check the status based on the db
			ignore_user_abort(true); 
			set_time_limit(0);			

			// retrieve page template
			$result = $this->db->getPstahl(array('NAME'=>'PAGE_TEMPLATE'));
			$tpl = $result!=FALSE ? $result['VALUE'] : '';

			// update common js
			$result = $this->db->getPstahl(array('NAME'=>'JS_COMMON'));
			$jscommon = $result!=FALSE ? $result['VALUE'] : '';
			$tpl = str_replace("{{common.js}}", $jscommon, $tpl);

			// update common css
			$result = $this->db->getPstahl(array('NAME'=>'CSS_COMMON'));
			$csscommon = $result!=FALSE ? $result['VALUE'] : '';
			$tpl = str_replace("{{common.css}}", $csscommon, $tpl);
			
			$config['BLOGPERPAGE'] = 5;
			$config['ARCHIVEPATH'] = $config['BASEPATH'] . 'archives/';
			$config['ARCHIVEURL'] = $config['BASEURL'] . 'archives/';
			$config['PAGESPATH'] = $config['BASEPATH'] . 'pages/';
			$config['PAGESURL'] = $config['BASEURL'] . 'pages/';

			// Todo on this section.
			$this->db->opendb();

			// 2.1 extract row count
			$sql = 'SELECT COUNT(*) AS COUNT FROM BLOG WHERE STATUS="P" AND CONTENT_TYPE="B" ';
			$result = $this->db->query($sql);
			$row = $result->fetchArray(SQLITE3_ASSOC);
			$config['BLOGTOTALCOUNT'] = $row['COUNT'];

			// generate directories
			$this->generateDirectory($config['ARCHIVEPATH']); 
			$this->generateDirectory($config['PAGESPATH']); 

			$config['BLOGTOTALPAGES'] = ceil( $config['BLOGTOTALCOUNT'] / $config['BLOGPERPAGE'] );

			for($i=1; $i<=$config['BLOGTOTALPAGES']; $i++) {
				$this->generateDirectory($config['PAGESPATH'] . $i . '/');
			}

			$sql = 'SELECT BLOG_ID,TITLE,SEGMENT,PUBLISH_DTTM,CONTENT,CONTENT_SUMMARY FROM BLOG WHERE STATUS="P" AND CONTENT_TYPE="B" ORDER BY DATETIME(PUBLISH_DTTM) DESC';
			$result = $this->db->query($sql);
			$count = 1; $curpage = 1;
			$archive_indexes = array();
			$pages_indexes = array();
			$resultarray = array();

			while($row = $result->fetchArray(SQLITE3_ASSOC) ) {
				array_push($resultarray, $row);
			}

			foreach ($resultarray as $rowkey => $row) {
				// identify publish datetime, segment sufix
				list($YEAR, $MONTH, $DAY) = explode('-',explode(' ', $row['PUBLISH_DTTM'])[0]) ;
				$PUBLISHDTTM_TOTIME = strtotime("$MONTH/$DAY/$YEAR");
				$SEGMENT_SUFIX = $row['SEGMENT'] . "-" . substr(filter_var($row['BLOG_ID'], FILTER_SANITIZE_NUMBER_INT), 0, 6); 
				$SUMMARY_CONTENT = $row['CONTENT_SUMMARY'];
				$SUMMARY_CONTENT = $photoService->replacePhotoCode($SUMMARY_CONTENT);

				// generate directories and file on each segment			
				$SEGMENT_PATH = $config['ARCHIVEPATH']."$YEAR/$MONTH/" . $SEGMENT_SUFIX . "/";
				$SEGMENT_URL = $config['ARCHIVEURL']."$YEAR/$MONTH/" . $SEGMENT_SUFIX . "/";
				$BLOG_PATH = $config['BASEURL']."$YEAR/$MONTH/" . $SEGMENT_SUFIX . "/";
				$SEGMENT_CONTENT = "<h1>".$row['TITLE']."</h1><p class=\"ui-published-date\">".date("l \of F d, Y", $PUBLISHDTTM_TOTIME)."</p><article class=\"ui-content\">".$row['CONTENT']."</article>";
				$this->generateDirectory($SEGMENT_PATH);

				$SEGMENT_CONTENT = str_replace("{{html.content}}",$SEGMENT_CONTENT,$tpl);
				$SEGMENT_CONTENT = str_replace("{{html.title}}"," | Archives | ". strtolower($row['TITLE']),$SEGMENT_CONTENT);
				$SEGMENT_CONTENT = str_replace("{{url.current}}",$SEGMENT_URL,$SEGMENT_CONTENT);
				$SEGMENT_CONTENT = $photoService->replacePhotoCode($SEGMENT_CONTENT);

				// begin: get next and previous content
				$prevblog = ""; $nextblog = ""; $prevbloglink = ""; $nextbloglink = "";
				if( $count == 1 && $config['BLOGTOTALCOUNT']>1 ) {
					$prev_index = $count;
					$SEGMENT_SUFIX2 = $resultarray[$prev_index]['SEGMENT'] . "-" . substr(filter_var($resultarray[$prev_index]['BLOG_ID'], FILTER_SANITIZE_NUMBER_INT), 0, 6); 
					$SEGMENT_URL2 = $config['ARCHIVEURL']."$YEAR/$MONTH/" . $SEGMENT_SUFIX2 . "/";
					$prevblog = "<a href=\"$SEGMENT_URL2\" class=\"ui-prev-blog\">" . $resultarray[$prev_index]['TITLE'] . "</a>";
					$prevbloglink = $SEGMENT_URL2;
				}
				else if( $count == $config['BLOGTOTALCOUNT'] && $config['BLOGTOTALCOUNT']>1 ) {
					$next_index = $count-2;
					$SEGMENT_SUFIX3 = $resultarray[$next_index]['SEGMENT'] . "-" . substr(filter_var($resultarray[$next_index]['BLOG_ID'], FILTER_SANITIZE_NUMBER_INT), 0, 6); 
					$SEGMENT_URL3 = $config['ARCHIVEURL']."$YEAR/$MONTH/" . $SEGMENT_SUFIX3 . "/";
					$nextblog = "<a href=\"$SEGMENT_URL3\" class=\"ui-next-blog\">" . $resultarray[$next_index]['TITLE'] . "</a>";					
					$nextbloglink = $SEGMENT_URL3;
				}
				else if( $count > 1 && $config['BLOGTOTALCOUNT']>2 ){
					$prev_index = $count;					
					$SEGMENT_SUFIX2 = $resultarray[$prev_index]['SEGMENT'] . "-" . substr(filter_var($resultarray[$prev_index]['BLOG_ID'], FILTER_SANITIZE_NUMBER_INT), 0, 6); 
					$SEGMENT_URL2 = $config['ARCHIVEURL']."$YEAR/$MONTH/" . $SEGMENT_SUFIX2 . "/";
					$prevblog = "<a href=\"$SEGMENT_URL2\" class=\"ui-prev-blog\">" . $resultarray[$prev_index]['TITLE'] . "</a>";
					$prevbloglink = $SEGMENT_URL2;
					
					$next_index = $count-2;
					$SEGMENT_SUFIX3 = $resultarray[$next_index]['SEGMENT'] . "-" . substr(filter_var($resultarray[$next_index]['BLOG_ID'], FILTER_SANITIZE_NUMBER_INT), 0, 6); 
					$SEGMENT_URL3 = $config['ARCHIVEURL']."$YEAR/$MONTH/" . $SEGMENT_SUFIX3 . "/";
					$nextblog = "<a href=\"$SEGMENT_URL3\" class=\"ui-next-blog\">" . $resultarray[$next_index]['TITLE'] . "</a>";
					$nextbloglink = $SEGMENT_URL3;
				}
				$SEGMENT_CONTENT = str_replace("{{url.prevblog}}",$prevblog,$SEGMENT_CONTENT);
				$SEGMENT_CONTENT = str_replace("{{url.nextblog}}",$nextblog,$SEGMENT_CONTENT);
				$SEGMENT_CONTENT = str_replace("{{url.prevblog.link}}",$prevbloglink,$SEGMENT_CONTENT);
				$SEGMENT_CONTENT = str_replace("{{url.nextblog.link}}",$nextbloglink,$SEGMENT_CONTENT);
				// end: get next and previous content

				// begin: custom templating
				$SEGMENT_CONTENT = str_replace("{{html.blog.title}}",$row['TITLE'],$SEGMENT_CONTENT);
				$SEGMENT_CONTENT = str_replace("{{html.blog.content}}",$row['CONTENT'],$SEGMENT_CONTENT);
				$SEGMENT_CONTENT = str_replace("{{html.blog.publishdate}}",date("l \of F d, Y", $PUBLISHDTTM_TOTIME),$SEGMENT_CONTENT);
				// end: custom templating


				$this->generateIndexFile($SEGMENT_PATH, $this->cleanUnusedCodes($config, $SEGMENT_CONTENT));

				// generate file on each month archive summary index			
				$MONTH_INDEX_CONTENT = "<li><a href=\"$SEGMENT_URL\"><span>".date("Y F d", $PUBLISHDTTM_TOTIME)."</span>: <span>".$row['TITLE']."</span></a></li>";
				$archive_indexes[$config['ARCHIVEPATH']."$YEAR/$MONTH/"] = $this->getset($archive_indexes,$config['ARCHIVEPATH']."$YEAR/$MONTH/") . $MONTH_INDEX_CONTENT;

				$YEAR_INDEX_CONTENT = "<li><a href=\"$SEGMENT_URL\"><span>".date("Y F", $PUBLISHDTTM_TOTIME)."</span>: <span>".$row['TITLE']."</span></a></li>";
				$archive_indexes[$config['ARCHIVEPATH']."$YEAR/"] = $this->getset($archive_indexes,$config['ARCHIVEPATH']."$YEAR/") . $YEAR_INDEX_CONTENT;

				$ARCHIVE_INDEX_CONTENT = "<li><a href=\"$SEGMENT_URL\"><span>".date("Y F", $PUBLISHDTTM_TOTIME)."</span>: <span>".$row['TITLE']."</span></a></li>";
				$archive_indexes[$config['ARCHIVEPATH']] = $this->getset($archive_indexes,$config['ARCHIVEPATH']) . $ARCHIVE_INDEX_CONTENT;

				$curpage = ceil( $count / $config['BLOGPERPAGE']);
				$ENTRY_PATH = $config['PAGESPATH'].$curpage."/";
				$ENTRY_CONTENT = "<h1><a href=\"$SEGMENT_URL\">".$row['TITLE']."</a></h1><p class=\"ui-published-date\">".
					date("l \of F d, Y", $PUBLISHDTTM_TOTIME)."</p><summary class=\"ui-content-summary\" >".$SUMMARY_CONTENT."</summary>";
				$ENTRY_CONTENT = str_replace("{{url.current}}",$SEGMENT_URL,$ENTRY_CONTENT);
				$pages_indexes[$curpage] = $this->getset($pages_indexes,intval($curpage)) . $ENTRY_CONTENT;

				$count++;
			}

			// 2.4 populate the archive indexes
			foreach ($archive_indexes as $KEY => $INDEX_CONTENT) {
				$INDEX_CONTENT = "<h1 class=\"archives\">Archives</h1><ul class=\"ui-archive-list\">".$INDEX_CONTENT."</ul>";
				$INDEX_CONTENT = str_replace("{{html.content}}", $INDEX_CONTENT, $tpl) ;
				$INDEX_CONTENT = $photoService->replacePhotoCode($INDEX_CONTENT);
				$this->generateIndexFile($KEY, $this->cleanUnusedCodes($config, $INDEX_CONTENT));
			}

			// 2.5 populate the pages indexes
			$_USE_PAGENUM = FALSE;
			$_USE_PAGEQUICK = TRUE;

			foreach ($pages_indexes as $KEY => $INDEX_CONTENT) {
				$INDEX_CONTENT = "<p>".$INDEX_CONTENT."</p>";
				$PAGES = "";			
				if( $_USE_PAGENUM ) {
					for($i=1;$i<=$config['BLOGTOTALPAGES'];$i++) {
						$PAGES = $PAGES . "<li><a" . ($KEY==$i ? " class=\"ui-active\"" : " href=\"$_PAGES_URL$i/\"") .">$i</a></li>";
						if($i>5 && $i<$config['BLOGTOTALPAGES']-5) { $i = $config['BLOGTOTALPAGES']-5; }
					}
					$PAGES = "<ul class=\"\">".$PAGES."</ul>";	
				}				

				$PAGES_QUICK = "";
				if($KEY==1 && $config['BLOGTOTALPAGES']>1) {					
					$PAGES_QUICK = "<div class=\"ui-older pull-right\"><span><a href=\"".$config['PAGESURL']. ($config['BLOGTOTALPAGES']-1) . "/\">Older &gt;&gt;&gt;</a></span></div>";
				}
				else if($KEY!=1 && $config['BLOGTOTALPAGES']>1 && $KEY!=$config['BLOGTOTALPAGES']) {
					$PAGES_QUICK = "<div class=\"ui-older pull-right\"><span><a href=\"".$config['PAGESURL']. ($config['BLOGTOTALPAGES']-$KEY+1-1) . "/\">Older &gt;&gt;&gt;</a></span></div>" .
					"<div class=\"ui-newer pull-left\"><span><a href=\"".$config['PAGESURL']. ($config['BLOGTOTALPAGES']-$KEY+1+1) . "/\">Newer &lt;&lt;&lt;</a></span></div>";					
				}
				else if($KEY==$config['BLOGTOTALPAGES'] && $config['BLOGTOTALPAGES']>1) {
					$PAGES_QUICK = "<div class=\"ui-newer pull-left\"><span><a href=\"".$config['PAGESURL']."2/\">Newer &lt;&lt;&lt;</a></span></div>";
				}

				if( $PAGES_QUICK!="" ) {
					$PAGES_QUICK = "<div class=\"clearfix\">" . $PAGES_QUICK . "</div>";
					if( $_USE_PAGEQUICK ) {
						$PAGES = $PAGES . $PAGES_QUICK;	
					}				
				}

				$INDEX_CONTENT = $INDEX_CONTENT . $PAGES;

				$INDEX_CONTENT = str_replace("{{html.content}}",$INDEX_CONTENT,$tpl);
				$INDEX_CONTENT = $photoService->replacePhotoCode($INDEX_CONTENT);
				$this->generateIndexFile($config['PAGESPATH'].($config['BLOGTOTALPAGES']-$KEY+1)."/", $this->cleanUnusedCodes($config, $INDEX_CONTENT));				
			}		
			copy($config['PAGESPATH'].$config['BLOGTOTALPAGES']."/index.html",$config['BASEPATH']."index.html");
			copy($config['PAGESPATH'].$config['BLOGTOTALPAGES']."/index.html",$config['PAGESPATH']."index.html");

			//-------------------------------
			// 2.6 generate pages content
			$sql = 'SELECT BLOG_ID,TITLE,SEGMENT,PUBLISH_DTTM,CONTENT,CONTENT_PATH FROM BLOG WHERE STATUS="P" AND CONTENT_TYPE="P" ORDER BY DATETIME(PUBLISH_DTTM) DESC';
			$result = $this->db->query($sql);
			while($row = $result->fetchArray(SQLITE3_ASSOC) ) {
				$SEGMENT_CONTENT = "<h1>".$row['TITLE']."</h1><p>".$row['CONTENT']."</p>";
				$SEGMENT_CONTENT = str_replace("{{html.content}}",$SEGMENT_CONTENT,$tpl);
				$SEGMENT_CONTENT = str_replace("{{html.title}}"," | ". strtolower($row['TITLE']),$SEGMENT_CONTENT);
				$SEGMENT_CONTENT = str_replace("{{url.current}}",$SEGMENT_URL,$SEGMENT_CONTENT);
				$SEGMENT_CONTENT = $photoService->replacePhotoCode($SEGMENT_CONTENT);
 
				$this->generateIndexFile($config['BASEPATH'].$this->helper->cleanPath( trim($row['CONTENT_PATH'],'/') ),$this->cleanUnusedCodes($config, $SEGMENT_CONTENT));
			}

			$this->db->closedb();

			// Update database to unlock export service
			$this->db->savePstahl(array('NAME'=>'EXPORT_RUNNING','VALUE'=>'N'));	
		}				
	}

	private function generateDirectory($dirpath) {
		$this->rrmdir($dirpath); 
		mkdir($dirpath, 0700, true);
	}


	private function generateIndexFile($path,$content) {
		if (!is_dir($path)) {
			mkdir($path, 0700, true);
		}	
		$file = fopen($path . 'index.html', "w") or die("Unable to open file!");	
		fwrite($file, $this->helper->removeExtraSpaces( $content ));
		fclose($file);
	}

	private function generatePhoto() {
		// generate photo if the photo currently doesnt exist			
	}

	private function cleanUnusedCodes( $config, $content ) {
		$content = str_replace("{{url.base}}",$this->getset($config,'BASEURL'),$content);
		$content = str_replace("{{url.archive}}",$this->getset($config,'ARCHIVEURL'),$content);
		$content = str_replace("{{url.pages}}",$this->getset($config,'PAGESURL'),$content);
		$content = str_replace("{{html.title}}","",$content);
		return $content;
	}

	private function getset($arr,$key) {
		return isset($arr) && is_array($arr) && array_key_exists($key,$arr) ? $arr[$key] : "";
	}

	private function rrmdir($dir) { 
		if (is_dir($dir)) { 
			$objects = scandir($dir); 
			foreach ($objects as $object) { 
				if ($object != "." && $object != "..") { 
					if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object); 
				} 
			} 
			reset($objects); 
			rmdir($dir); 
		} 
	} 

}


/**
 *  Classname: LoginService
 *  
 */
class LoginService {

	public $user;

	private $post;
	private $validator;
	private $helper;	

	function __construct(&$db, &$post, &$validator, &$helper) {
		$this->post = &$post;
		$this->validator = &$validator;
		$this->helper = &$helper;		
	}

	public function hasRequired() {
		if ( $this->validator->hasRequired(array('email','password')) ) {
			return TRUE;
		}
		return FALSE;
	}	

	public function checkUsernamePassword() {
		$accounts = json_decode(LOGIN_USER)->accounts;
		foreach($accounts as $key => $user) {  
			if( $this->post['email'] == $user->email && $this->post['password'] == $user->password ) {
				unset($user->password);
				$this->user = $user;
				return TRUE;
			}	
		}		
		return FALSE;		
	}
}



/**
 *  Classname: View
 *  Handles the front end display
 */
class View {

	private $helper;
	private $flash;

	function __construct( &$helper ) {
		$this->helper = $helper;		
	}

	public function redirect( $param ) {
		switch ( $param ) {
			case 'login.view': $this->loginRedirect(); break;		
			case 'admin.view': $this->adminRedirect(); break;		
			default: $this->loginRedirect(); break;
		}	
	}

	public function json( $data = array() ) {
		header('Content-Type: application/json');
		echo json_encode($data);
		die();
	}

	public function jsont( $data = array() ) {
		header('Content-Type: text/html');
		echo json_encode($data);
		die();
	}

	public function loginView( $data = array() ) {		
		$this->flashData( $data );	
		$_SESSION["view_redirect"] = 'login.view';
		header('Location: '. $this->helper->baseUrlPath() );
	}

	public function adminView( $data = array() ) {		
		$this->flashData( $data );	
		$_SESSION["view_redirect"] = 'admin.view';
		header('Location: '. $this->helper->baseUrlPath() );
	}

	public function photoView( $data = array() ) {		
		$fileExtension = 'jpg';

		switch( $fileExtension ) {
			case "gif": $ctype="image/gif"; break;
			case "png": $ctype="image/png"; break;
			case "jpeg":
			case "jpg": $ctype="image/jpeg"; break;
			default:
		}
		header('Content-type: ' . $ctype);	
		echo $data;
		die();
	}
	

	private function loginRedirect() {
		$data = $this->flashData();
		$render_html = $this->html_login_page;		
		$this->wp("{{html.title}}", SYSTEM_TITLE, $render_html);
		$this->wp("{{login.error.display}}", isset($data['error']) ? '' : 'hide', $render_html);		
		$this->render($render_html);
	}

	private function adminRedirect() {
		$data = $this->flashData();
		$render_html = $this->html_admin_page;		
		$this->wp("{{html.title}}", SYSTEM_TITLE, $render_html);		
		$this->wp("{{js.admin}}", $this->js_admin_page, $render_html);		
		$this->wp("{{css.admin}}", $this->css_admin_page, $render_html);
		$this->wp("{{json.config}}", $this->sy(), $render_html);
		$this->render($render_html);
	}

	private function render( &$render_html ) {		
		$render_html = str_replace("{{js.module}}", "", $render_html);
		$render_html = str_replace("{{base.url}}", $this->helper->cleanPath(SYSTEM_BASEURL), $render_html);
		echo $this->helper->removeExtraSpaces( $render_html ); 
	}
	
	private function flashData( $data = NULL ) {
		$ret = "";
		if( $data != NULL ) {
			$_SESSION["flash_data"] = $data;
			$ret = $_SESSION["flash_data"];
		}		
		else {
			$ret = isset($_SESSION["flash_data"]) ? $_SESSION["flash_data"] : "";
			unset( $_SESSION["flash_data"] );
		}				
		$this->flash = $ret;
		return $ret;
	}

	private function sy() {
		$config = json_decode(SYSTEM_CONFIG);
		$sys = array(); 
		$count = 0;		
		$sys['active'] = $_SESSION["user_config"]->alias;
		foreach($config->path as $key => $value) {    			
			if(isset($value->alias)) {
				$sys[$count] = $value->alias;
				$count++;	
			}
			
		}
		return json_encode($sys);
	}

	private function wp($t, $v, &$c)  {
		$c = str_replace($t, $v, $c);
	}

	private function gf($key, $ret="", $rev=FALSE) {
		if($rev==FALSE) { 
			return isset($this->flash[$key]) ? $this->flash[$key] : $ret;
		}
		else {
			return isset($this->flash[$key]) ? $ret : $this->flash[$key];	
		}
	}	

	private $html_login_page =<<<EOF
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Pstahl, Php Static Website Html File Generator">
<meta name="author" content="Joey Albert Abano">
<title>{{html.title}} : Content Development Tool</title>
<link href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
<style type="text/css"> div.container.container-login { max-width:304px; } </style>
<script src="//google.com/recaptcha/api.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.2.4/jquery.min.js" type="text/javascript"></script>		
<script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/ace.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/mode-css.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/mode-javascript.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/mode-html.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/ext-language_tools.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/theme-tomorrow.js" type="text/javascript"></script>
</head>
<body>
	<div class="container container-login">
		<form class="form-horizontal" action="{{base.url}}" method="POST">
			<input type="email" name="email" class="hide"><input type="password" name="password" class="hide">		
			<div class="form-group">
			<h2>Log-In</h2>
			</div>
			<div class="form-group">
			<label for="email" class="sr-only">Email address</label>		
			<input name="email" type="email" class="form-control" autocomplete="off" placeholder="Email address" required autofocus>
			</div>
			<div class="form-group">
			<label for="password" class="sr-only">Password</label>
			<input name="password" type="password" class="form-control" autocomplete="off" placeholder="Password" required>
			<div class="text-danger {{login.error.display}}">Invalid username or password.</div>
			</div>
			<div class="form-group">
			<div class="g-recaptcha" data-sitekey="6LeZEAoUAAAAAHGP4bvwK9icYuMAffNLofjhOEIg"></div>
			</div>		
			<div class="form-group">			
			<button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
			</div>
		</form>
	</div>	
</body>
</html>
EOF;

	private $js_admin_page =<<<EOF
var conf = new Conf();
var blog = new Blog();
var pages = new Pages();
var photo = new Photo();
var templates = new Templates();
var deploy = new Deploy();
var datetime = new DateTime();

/* 1. Prepare menu navigation */
$(function(){
	$('ul.navbar-nav li.menu').click(function(){
		$('ul.navbar-nav li').removeClass('active');
		$(this).addClass('active');
		$( 'div.container.content' ).addClass('hide');
		$( $(this).attr('attr-target') ).removeClass('hide');
	});
	
	$('a.btn.menu').click(function(){
		$('form div.form-group').removeClass('has-success').removeClass('has-error');
        $('form div.form-group span.glyphicon').removeClass('glyphicon-remove').removeClass('glyphicon-ok');
		$( 'div.container.content' ).addClass('hide');
		$( $(this).attr('attr-target') ).removeClass('hide');

		blog.fillform({id:'',title:'',tags:'',publishdate:datetime.current(),status:'D',preview:'',content:''});
		pages.fillform({id:'',title:'',tags:'',publishdate:datetime.current(),status:'D',preview:'',content:''});
	});

	if(window.location.hash) {
		var hash = window.location.hash.substring(1);
		$('ul.navbar-nav li.'+hash).trigger('click');
		$('a.'+hash).trigger('click');
	}
	else {
		$('ul.navbar-nav li.blog').trigger('click');	
	}
	

});

/* 2. Prepare blog list. Prepare all datetime pickers */
$(function() { 	
	conf.init();
	blog.init();
	pages.init();
	photo.init();
	templates.init();
	deploy.init();

	blog.list();
	pages.list();
	photo.list();
	templates.list();
	deploy.list();

	setTimeout(function(){ 
		datetime.datetimepicker();
	},2000);
});


/* 3. Define all submit buttons */
$(function(){
	$('a.submit').click(function(e){			
		e.preventDefault();
		var form = $(this).parents('form:first'); form = $(form);
		var haserror = false;
		form.find('div.form-group').each(function(i,fg){
			fg = $(fg);
			fg.find('.required').each(function(i,v){
				if( $(v).val().trim() == '') { 
					fg.removeClass('has-success').addClass('has-error'); haserror = true; 						
					fg.find('span.glyphicon').removeClass('glyphicon-ok').addClass('glyphicon-remove');
				}
				else {
					fg.removeClass('has-error').addClass('has-success');
					fg.find('span.glyphicon').removeClass('glyphicon-remove').addClass('glyphicon-ok');
				}
			});					
		});
		if(!haserror) {			
			var s = \$('#modal-loading div.progress-bar span'); s.html(1);
			var c=1,l=function(){ 				
				if(s.html()<90){ c=c+3; \$('#modal-loading div.progress-bar').width(c+'%'); s.html(c); setTimeout(l,20); }
			}; l();
			form.append('<input name="action" type="hidden" value="' + $(this).attr('attr-action') + '"/>');						
			$('#modal-loading').modal({show:true, keyboard:false, backdrop:'static'});
			$('#modal-loading').data('form',form);

			if(form.attr('name')=='photo-upload' || form.attr('name')=='photo-reupload') { form.attr('enctype','multipart/form-data'); }			

			form.submit();
		}
		
	});

	$('iframe.catch').on('load', function(){        
		var form = $('#modal-loading').data('form');
		if(form){
			form = $(form);
			switch(form.attr('name')) {
				case 'database-select': blog.list(); pages.list(); photo.list(); templates.list(); deploy.list();break;
				case 'blog-create': blog.list(); break;
				case 'pages-create': pages.list(); break;
				case 'photo-upload': photo.list(); break;
				case 'photo-reupload': photo.list(); break;
			}	

			form.find('div.form-group').each(function(i,fg){ fg = $(fg);
				fg.removeClass('has-error').removeClass('has-success');
				fg.find('span.glyphicon').removeClass('glyphicon-remove').removeClass('glyphicon-ok');
			});
		}		

		var p = \$('#modal-loading div.progress-bar'), s = \$('#modal-loading div.progress-bar span'); p.width('100%'); s.html(100);
		setTimeout(function(){ $('#modal-loading').modal('hide'); p.width('1%'); s.html(1); },2000);        
	});

});


/* 4. Define select dom value based on attr-selected  */
$(function() { 		
	$('select').each(function(i,e){ 
		var v = $(e).attr('attr-selected'); 
		if(v && v!=''){ $(e).val(v); } 
	});
});

/*  Classname: Conf
	Handles configuration processes.
*/

function Conf() { 
	this.config = {{json.config}};
}

Conf.prototype.init = function() {
	var self = this;
	$.each( self.config, function( key, value ) {
		if(key!='active'){
			var v='<option value="'+key+'">'+value+'</option>';
			if(value == self.config.active) {
				v='<option value="'+key+'" selected>'+value+'</option>';
			}
			$('div.content.database-list select').append(v);	  			
		}		  		
	});	
};	

/*  Classname: Blog
	Handles blog processes.
*/

function Blog() { }

Blog.prototype.init = function() {
	ace.require("ace/ext/language_tools");

	var editor_preview = ace.edit("blog-preview");		
	editor_preview.session.setMode('ace/mode/html');
	editor_preview.\$blockScrolling = Infinity;
	editor_preview.setOptions({ enableBasicAutocompletion:true, enableSnippets:true, enableLiveAutocompletion:false, wrap:true });
	editor_preview.setTheme("ace/theme/tomorrow");
	editor_preview.setValue( $('#blog-preview-text').val(), 1 );

	var editor_content = ace.edit("blog-content");
	editor_content.session.setMode('ace/mode/html');
	editor_content.\$blockScrolling = Infinity;
	editor_content.setOptions({ enableBasicAutocompletion:true, enableSnippets:true, enableLiveAutocompletion:false, wrap:true });
	editor_content.setTheme("ace/theme/tomorrow");
	editor_content.setValue( $('#blog-content-text').val(), 1 );

	setInterval(function(){ 				
		$('#blog-preview-text').val(editor_preview.getValue());					
		$('#blog-content-text').val(editor_content.getValue());				
	}, 500);

	$('div.container-create-blog').addClass('in');
};

Blog.prototype.fillform = function(form){
	$('div.content.blog-create input[name="id"]').val(form.id);
	$('div.content.blog-create input[name="title"]').val(form.title);
	$('div.content.blog-create input[name="tags"]').val(form.tags);
	$('div.content.blog-create input[name="publishdate"]').val(form.publishdate);
	$('div.content.blog-create select[name="status"]').val(form.status);
	
	var editor_preview = ace.edit("blog-preview");
	$('div.content.blog-create textarea[name="preview"]').val(form.preview);		
	editor_preview.setValue( $('#blog-preview-text').val(), 1 );

	var editor_content = ace.edit("blog-content");
	$('div.content.blog-create textarea[name="content"]').val(form.content);
	editor_content.setValue( $('#blog-content-text').val(), 1 );
};

Blog.prototype.list = function(){
	var self = this;

	$.post({url:'{{base.url}}', type:'POST', data:'action=blog.list'}).done(function(json){ 
		var lt = $('div.content.blog div.container-blog-list');
		var tb = $(document.createElement('table'));
		lt.empty();
		tb.addClass('table table-bordered table-hover table-condensed blog-list');
		tb.append('<thead></thead><tbody></tbody>');
		
		if(json&&json.table&&json.table.length>0) {
			var a = json.table;
			tb.find('thead').empty().append('<tr><th>Title</th><th>Segment</th><th>Publish Status</th><th style="width:160px;">Action</th></tr>');
			tb.find('tbody').empty();
			for(i=0;i<a.length;i++){
				var r = a[i];
				var c = '<td>'+r.TITLE+'</td><td>'+r.SEGMENT+'<br><span class="label label-default">/archive</span></td><td>'+r.PUBLISH_DTTM+'<br>'+(r.STATUS=='P'?'<span class="label label-primary">Published</span>':'<span class="label label-warning">Draft</span>')+'</td>';
				var b = '<td><a class="btn btn-primary modify" attr-target-id="'+r.ID+'">Modify</a>&nbsp;<a class="btn btn-primary clone" attr-target-id="'+r.ID+'">Clone</a></td>';
				tb.find('tbody').append('<tr class="'+(r.STATUS=='P'?'':'warning')+'">'+c+b+'</tr>');
			}						
		}	
		else {
			tb.find('thead').empty().append('<tr><th></th></tr>');
		}			

		lt.append(tb);
		self.buttonEvents({tb:tb});	
		self.datatable({tb:tb,size:json.table.length});

	});	
};	

Blog.prototype.buttonEvents = function(o){
	var self = this;
	o.tb.find('a.modify').click(function(){
		var id = $(this).attr('attr-target-id');
		$('#modal-loading').modal({show:true, keyboard:false, backdrop:'static'});
		$.post({url:'{{base.url}}', type:'POST', data:'action=blog.create.get&id='+id}).done(function(json){
			$('a.menu.blog-create').trigger('click');				
			self.fillform(json);
			$('#modal-loading').modal('hide');			
		});	
	});
	o.tb.find('a.clone').click(function(){
		var id = $(this).attr('attr-target-id');
		$('#modal-loading').modal({show:true, keyboard:false, backdrop:'static'});
		$.post({url:'{{base.url}}', type:'POST', data:'action=blog.create.get&id='+id}).done(function(json){
			$('a.menu.blog-create').trigger('click');	
			json.id='';			
			self.fillform(json);
			$('#modal-loading').modal('hide');			
		});	
	});
};	

Blog.prototype.datatable = function(o){
	var t = o.tb.DataTable();
	if(o.size>0) {		
		t.order([[2,"desc"]]).draw();		
	}
	$('div.dataTables_filter').addClass('text-right');
	$('div.dataTables_paginate.paging_simple_numbers').addClass('text-right');
};	


/*  Classname: Pages
	Handles blog processes.
*/


function Pages() { }

Pages.prototype.init = function(){
	ace.require("ace/ext/language_tools");

	var editor_content = ace.edit("pages-content");
	editor_content.session.setMode('ace/mode/html');
	editor_content.\$blockScrolling = Infinity;
	editor_content.setOptions({ enableBasicAutocompletion:true, enableSnippets:true, enableLiveAutocompletion:false, wrap:true });
	editor_content.setTheme("ace/theme/tomorrow");
	editor_content.setValue( $('#pages-content-text').val(), 1 );

	setInterval(function(){ 				
		$('#pages-content-text').val(editor_content.getValue());				
	}, 500);

	$('div.container-create-pages').addClass('in');
};

Pages.prototype.fillform = function(form){
	$('div.content.pages-create input[name="id"]').val(form.id);
	$('div.content.pages-create input[name="title"]').val(form.title);
	$('div.content.pages-create input[name="tags"]').val(form.tags);
	$('div.content.pages-create input[name="contentpath"]').val(form.contentpath);
	$('div.content.pages-create input[name="publishdate"]').val(form.publishdate);	
	$('div.content.pages-create select[name="status"]').val(form.status);
	
	var editor_content = ace.edit("pages-content");
	$('div.content.pages-create textarea[name="content"]').val(form.content);
	editor_content.setValue( $('#pages-content-text').val(), 1 );
};

Pages.prototype.list = function(){
	var self = this;

	$.post({url:'{{base.url}}', type:'POST', data:'action=pages.list'}).done(function(json){
		var lt = $('div.content.pages div.container-pages-list');
		var tb = $(document.createElement('table'));
		lt.empty();
		tb.addClass('table table-bordered table-hover table-condensed blog-list');
		tb.append('<thead></thead><tbody></tbody>');
		
		if(json&&json.table&&json.table.length>0) {
			var a = json.table;
			tb.find('thead').empty().append('<tr><th>Title</th><th>Segment</th><th>Publish Status</th><th>Action</th></tr>');
			tb.find('tbody').empty();
			for(i=0;i<a.length;i++){
				var r = a[i];
				var c = '<td>'+r.TITLE+'</td><td>'+r.SEGMENT+'<br><span class="label label-default">'+r.CONTENT_PATH+'</span></td><td>'+r.PUBLISH_DTTM+'<br>'+(r.STATUS=='P'?'<span class="label label-primary">Published</span>':'<span class="label label-warning">Draft</span>')+'</td>';
				var b = '<td><a class="btn btn-primary modify" attr-target-id="'+r.ID+'">Modify</a></td>';
				tb.find('tbody').append('<tr class="'+(r.STATUS=='P'?'':'warning')+'">'+c+b+'</tr>');
			}						
		}	
		else {
			tb.find('thead').empty().append('<tr><th></th></tr>');
		}			

		lt.append(tb);
		self.buttonEvents({tb:tb});	
		self.datatable({tb:tb,size:json.table.length});		
	});	

};

Pages.prototype.buttonEvents = function(o){
	var self = this;
	o.tb.find('a.modify').click(function(){
		var id = $(this).attr('attr-target-id');
		$('#modal-loading').modal({show:true, keyboard:false, backdrop:'static'});
		$.post({url:'{{base.url}}', type:'POST', data:'action=pages.create.get&id='+id}).done(function(json){
			$('a.menu.pages-create').trigger('click');	console.debug(json);			
			self.fillform(json);			
			$('#modal-loading').modal('hide');
		});	
	});
};	

Pages.prototype.datatable = function(o){
	var t = o.tb.DataTable();
	if(o.size>0) {		
		t.order([[2,"desc"]]).draw();		
	}
	$('div.dataTables_filter').addClass('text-right');
	$('div.dataTables_paginate.paging_simple_numbers').addClass('text-right');
};	


/*  Classname: Photo
	
*/
function Photo() { }

Photo.prototype.init = function() {	
};

Photo.prototype.list = function() {
	var self = this;
	var e = $('div.content.photo div.container-photo-list');
	e.empty();

	$.post({url:'{{base.url}}', type:'POST', data:'action=photo.list'}).done(function(json){
		if(json&&json.table&&json.table.length>0) { var t=json.table;			
			for(i=0;i<t.length;i++){
				var mg = $('<img src="{{base.url}}?photo='+t[i].PHOTO_ID+'&size=thumb" border=0 class="img-photo img-thumbnail img-responsive" >');
				mg.css('opacity',0);
				mg.load(function(e){ $(this).animate({ opacity:1},1000); });				
				var wp = $('<div class="wrap">').append(mg);
				var dv = $('<div class="img-container">').append(wp).append('<div class="info">Embed: <code>{{photo:'+t[i].PHOTO_ID+'}}</code><br>Created: '+t[i].CREATED_DTTM+' <span class="hide">, '+t[i].DESCRIPTION+'</span></div>');
				e.append(dv);
				dv.click(editphoto);
				dv.data('photo',t[i]);
			}
		}
	});		

	function editphoto() {
		var dv = $(this);
		$('#modal-edit-photo').modal({show:true, keyboard:true });
		$('#modal-edit-photo').find('img').attr('src',dv.find('img').attr('src').replace('thumb','orig'));
		$('#modal-edit-photo').find('div.info').html('<br>'+dv.find('div.info').html().replace('<br>','. ').replace('hide',''));		
		$('#modal-edit-photo input[name="photoid"]').val( dv.data('photo').PHOTO_ID );
	}
};

/*  Classname: Templates
	
*/
function Templates() { }

Templates.prototype.init = function() {
	$('a.load-page-template').click(function(){ $('div.template-group').addClass('hide'); $('div.page-template').removeClass('hide'); });
	$('a.load-javascript-common').click(function(){ $('div.template-group').addClass('hide'); $('div.javascript-common').removeClass('hide'); });
	$('a.load-css-common').click(function(){ $('div.template-group').addClass('hide'); $('div.css-common').removeClass('hide'); });
	$('a.load-page-template').trigger('click');

	ace.require("ace/ext/language_tools");

	var editor_page_template = ace.edit("page-template-content");
	editor_page_template.session.setMode('ace/mode/html');
	editor_page_template.\$blockScrolling = Infinity;
	editor_page_template.setOptions({ enableBasicAutocompletion:true, enableSnippets:true, enableLiveAutocompletion:false, wrap:true });
	editor_page_template.setTheme("ace/theme/tomorrow");
	editor_page_template.setValue( $('#page-template-content-text').val(), 1 );

	var editor_javascript_common= ace.edit("javascript-common-content");
	editor_javascript_common.session.setMode('ace/mode/javascript');
	editor_javascript_common.\$blockScrolling = Infinity;
	editor_javascript_common.setOptions({ enableBasicAutocompletion:true, enableSnippets:true, enableLiveAutocompletion:false, wrap:true });
	editor_javascript_common.setTheme("ace/theme/tomorrow");
	editor_javascript_common.setValue( $('#javascript-common-content-text').val(), 1 );

	var editor_css_common = ace.edit("css-common-content");
	editor_css_common.session.setMode('ace/mode/css');
	editor_css_common.\$blockScrolling = Infinity;
	editor_css_common.setOptions({ enableBasicAutocompletion:true, enableSnippets:true, enableLiveAutocompletion:false, wrap:true });
	editor_css_common.setTheme("ace/theme/tomorrow");
	editor_css_common.setValue( $('#css-common-content-text').val(), 1 );

	setInterval(function(){ 				
		$('#page-template-content-text').val(editor_page_template.getValue());
		$('#javascript-common-content-text').val(editor_javascript_common.getValue());
		$('#css-common-content-text').val(editor_css_common.getValue());
	}, 500);

};


Templates.prototype.list = function() {	
	var self = this;

	$.post({url:'{{base.url}}', type:'POST', data:'action=templates.get'}).done(function(json){
		var editor_page_template = ace.edit("page-template-content");
		if(json.PAGE_TEMPLATE) {						
			editor_page_template.setValue( json.PAGE_TEMPLATE, 1 );	
		}
		else {
			editor_page_template.setValue( '', 1 );		
		}

		var editor_javascript_common= ace.edit("javascript-common-content");
		if(json.JS_COMMON) {			
			editor_javascript_common.setValue( json.JS_COMMON, 1 );
		}
		else {
			editor_javascript_common.setValue( '', 1 );	
		}

		var editor_css_common = ace.edit("css-common-content");
		if(json.CSS_COMMON) {		
			editor_css_common.setValue( json.CSS_COMMON, 1 );
		}
		else {
			editor_css_common.setValue( '', 1 );
		}
	});	
};


/*  Classname: Deploy
	
*/
function Deploy() { }

Deploy.prototype.init = function() {
};

Deploy.prototype.list = function() {
	var self = this;

	$.post({url:'{{base.url}}', type:'POST', data:'action=deploy.get'}).done(function(json){

		var isrunning = json.EXPORT_RUNNING;
		if(isrunning=='Y'){
			$('div.content.deploy input').attr('readonly','readonly');
		}

		$('div.content.deploy input[name="preview-path"]').val(json.TEST_EXPORT_PATH);
		$('div.content.deploy input[name="preview-url"]').val(json.TEST_BASE_URL);
		$('div.content.deploy input[name="prod-path"]').val(json.PROD_EXPORT_PATH);
		$('div.content.deploy input[name="prod-url"]').val(json.PROD_BASE_URL);

	});	
};


/*  Classname: Datetime
	Common datetime processes
*/

function DateTime() { }

DateTime.prototype.current = function() {
	var d=new Date();
	var m=d.getMonth()+1, a=d.getDate(), h=d.getHours(), i=d.getMinutes(), s=d.getSeconds();
	return d.getFullYear()+'-'+(m<10?'0':'')+m+'-'+(a<10?'0':'')+a+' '+(h<10?'0':'')+h+':'+(i<10?'0':'')+i+':'+(s<10?'0':'')+s;
};

DateTime.prototype.datetimepicker = function(){
	$('input.datetime').each(function(index,element){		
		$(element).datetimepicker({format:'Y-m-d H:i:s'}); /* i. set the default date time */
		if($(element).val()=='') { $(element).val(datetime.current()); }					
		/* setTimeout(function(){ $('.xdsoft_datetimepicker .xdsoft_datepicker').width( $(element).width()-28 ); },500);  ii. adjust datetime picker width */
	}); 
};


EOF;

	private $css_admin_page =<<<EOF
div.control a.btn, div.control select, table a.btn { margin-bottom:4px; }
div.control a.btn:not(.last), div.control select:not(.last) { margin-right:4px; }
div.editor { position:relative; width:100%; height:320px; }	
table td:last-child, table th:last-child  { width:110px; text-align:center;  }
span.btn-file { position: relative; overflow: hidden; margin-bottom:4px; margin-right:4px; }
span.btn-file input[type=file] { background: white; cursor: inherit; display: block; font-size: 100px; min-height: 100%; min-width: 100%; opacity: 0; outline: none; position: absolute; right:0; text-align: right; filter: alpha(opacity=0); top:0; }
table th { background:#337ab7; color:#fff; font-weight:normal; text-transform:uppercase; }
table,table th,table td { border:solid 1px #eee !important; }
table,table th,table td { font-size:12px !important; }
table btn,table a { font-size:10px !important; text-transform:uppercase; }
img.img-photo.img-thumbnail { border:solid 1px #eee; border-radius:0px; margin-left:4px; margin-bottom:4px; width:180px; }
div.img-container { display:inline-block; }
div.img-container div.info { color:#777; font-size:12px; margin-left:4px; text-align:center; }
div.img-container div.wrap { background:none; overflow:hidden; }				
div.img-container div.wrap img { -webkit-transition: all 0.2s ease; -moz-transition: all 0.2s ease; -o-transition: all 0.2s ease; -ms-transition: all 0.2s ease; transition: all 0.2s ease; }
div.img-container div.wrap:hover img { -webkit-transform:scale(1.50); -moz-transform:scale(1.50); -ms-transform:scale(1.50); -o-transform:scale(1.50); transform:scale(1.50); zoom: 1; filter: alpha(opacity=50); opacity: 0.5; }



EOF;

	private $html_admin_page =<<<EOF
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Pstahl, Php Static Website Html File Generator">
<meta name="author" content="Joey Albert Abano">
<title>{{html.title}} : Content Development Tool</title>
<link href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
<link href="//cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.4/build/jquery.datetimepicker.min.css" rel="stylesheet">
<style type="text/css">{{css.admin}}</style>	
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.2.4/jquery.min.js" type="text/javascript"></script>		
<script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js" type="text/javascript"></script>
<script src="//cdn.datatables.net/s/bs/dt-1.10.10,r-2.0.0,sc-1.4.0,se-1.1.0/datatables.min.js" type="text/javascript"></script>		
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.4/build/jquery.datetimepicker.full.min.js" type="text/javascript"></script>	
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/ace.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/mode-css.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/mode-javascript.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/mode-html.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/ext-language_tools.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.2.5/theme-tomorrow.js" type="text/javascript"></script>

<script type="text/javascript">{{js.admin}}</script>
</head>
<body>	
	<div class="container">

		<!-- ./menu -->
		<div class="container menu">
			<nav class="navbar navbar-default">
				<div class="container-fluid">
					<div class="navbar-header">
						<button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navmenu">
							<span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span> 
						</button>
					</div>
					<div id="navmenu" class="collapse navbar-collapse">
						<ul class="nav navbar-nav">	
							<li class="menu blog active" attr-target=".content.blog"><a href="{{base.url}}#blog">Blog</a></li>
							<li class="menu pages" attr-target=".content.pages"><a href="{{base.url}}#pages">Pages</a></li> 
							<li class="menu photo" attr-target=".content.photo"><a href="{{base.url}}#photo">Photo</a></li> 
							<li class="menu packages" attr-target=".content.packages"><a href="{{base.url}}#packages">Packages</a></li> 
							<li class="menu templates" attr-target=".content.templates"><a href="{{base.url}}#templates">Templates</a></li>       
							<li class="menu deploy" attr-target=".content.deploy"><a href="{{base.url}}#deploy">Deploy</a></li> 
						</ul>
						<ul class="nav navbar-nav navbar-right">
							<li class="dropdown">
								<a class="dropdown-toggle" data-toggle="dropdown" href="#dropdown">Settings</a>
								<ul class="dropdown-menu">
									<li><a href="#credits"><span class="glyphicon glyphicon-info-sign"></span>&nbsp;&nbsp;About Pstahl</a></li>
									<li class="menu database-list" attr-target=".content.database-list"><a href="{{base.url}}#database-list"><span class="glyphicon glyphicon-hdd"></span>&nbsp;&nbsp;Choose Database</a></li>
									<li><a href="{{base.url}}?signout=true"><span class="glyphicon glyphicon-log-out"></span>&nbsp;&nbsp;Sign-out</a></li>          
								</ul>
							</li>	
						</ul>
					</div>
				</div>
			</nav>
		</div>
		<!-- ./menu -->

		<!-- ./database-list -->
		<div class="container content database-list hide">
		<form name="database-select" action="{{base.url}}" method="POST" target="action-catch">
			<div class="control">
				<div class="row">
					<div class="col-md-12">
						<div class="form-inline">
						<a class="btn btn-primary btn-sm save submit" attr-action="database.select">Choose Database</a>
						<select name="db" class="form-control input-sm"></select>
						</div>
					</div>					
				</div>								
			</div>
			<hr>
			<div class="container-database-list"></div>
		</form>
		</div>
		<!-- ./end:database-list -->

		<!-- ./blog -->
		<div class="container content blog hide">
			<div class="control">
				<a class="btn btn-primary btn-sm menu blog-create" href="{{base.url}}#blog-create" attr-target=".content.blog-create">Create Blog</a>
			</div>
			<hr>
			<div class="container-blog-list">				
				<table class="table table-bordered table-hover table-condensed blog-list" cellpadding=0 cellspacing=0 border=0>
					<thead></thead>
					<tbody></tbody>
				</table>
			</div>
		</div>
		<!-- ./end:blog -->

		<!-- ./blog-create -->
		<div class="container content blog-create hide">
		<form name="blog-create" action="{{base.url}}" method="POST" target="action-catch">
			<div class="control">
				<div class="row">
					<div class="col-md-6">
						<div class="form-inline">
						<a class="btn btn-default btn-sm menu back" href="{{base.url}}#blog" attr-target=".content.blog">&lt;&lt;&nbsp;Back to Blog List</a>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-inline text-right">
						<select class="form-control input-sm"><option selected>Choose Version</option></select>
						<a class="btn btn-primary btn-sm menu">Load Version</a>
						<a class="btn btn-primary btn-sm save submit last" attr-action="blog.create.save">Save Blog</a>
						</div>
					</div>
				</div>
			</div>
			<hr>
			<div class="container-blog-create">				
				<div class="form-group has-feedback">
					<label>Title</label><input name="title" type="text" class="form-control input-sm required" placeholder="Blog Title" value="" />	
					<input name="id" type="hidden" class="hidden" value="" />
					<span class="glyphicon form-control-feedback"></span>
				</div>
				<div class="row">
					<div class="col-md-5">
						<div class="form-group has-feedback">
							<label>Tags</label><input name="tags" type="text" class="form-control input-sm required" placeholder="Blog Category / Tags" value="" />
							<span class="glyphicon form-control-feedback"></span>
						</div>
					</div>
					<div class="col-md-4">				
							<div class="form-group has-feedback">
							<label class="control-label">Publish Date</label>				
							<input name="publishdate" type="text" class="form-control input-sm datetime required" placeholder="Publish Date Time" value=""/>
							<span class="glyphicon glyphicon-calendar form-control-feedback"></span>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group has-feedback">
						<label>Status</label>
						<select name="status" class="form-control input-sm required"><option value="D">Draft</option><option value="P">Publish</option><option value="R">Remove</option></select>				
						</div>
					</div>			
				</div>
				<div class="content form-group">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group has-feedback">
								<label>Preview</label>
								<span class="glyphicon form-control-feedback"></span>
								<div id="blog-preview" class="editor ace-form"></div>				
								<textarea id="blog-preview-text" name="preview" class="hide required"></textarea>
							</div>
						</div>
						
						<div class="col-md-6">
							<label>Content</label>
							<div class="form-group has-feedback">
								<span class="glyphicon form-control-feedback"></span>
								<div id="blog-content" class="editor ace-form"></div>				
								<textarea id="blog-content-text" name="content" class="hide required"></textarea>
							</div>					
						</div>
					</div>
				</div>
			</div>
		</form>
		</div>
		<!-- ./end:blog-create -->

		<!-- ./pages -->
		<div class="container content pages hide">
			<div class="control">
				<a class="btn btn-primary btn-sm menu pages-create" href="{{base.url}}#pages-create" attr-target=".content.pages-create">Create Pages</a>
			</div>
			<hr>
			<div class="container-pages-list">
				<table class="table table-bordered table-hover table-condensed pages-list" cellpadding=0 cellspacing=0 border=0>
					<thead></thead>
					<tbody></tbody>
				</table>
			</div>
		</div>		
		<!-- ./end:pages -->

		<!-- ./pages-create -->
		<div class="container content pages-create hide">
		<form name="pages-create" action="{{base.url}}" method="POST" target="action-catch">
			<div class="control">
				<div class="row">
					<div class="col-md-6">
						<div class="form-inline">
						<a class="btn btn-default btn-sm menu back" href="{{base.url}}#pages" attr-target=".content.pages">&lt;&lt;&nbsp;Back to Pages List</a>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-inline text-right">
						<select class="form-control input-sm"><option selected>Choose Version</option></select>
						<a class="btn btn-primary btn-sm menu">Load Version</a>
						<a class="btn btn-primary btn-sm save submit last" attr-action="pages.create.save">Save Pages</a>
						</div>
					</div>
				</div>
			</div>
			<hr>
			<div class="container-pages-create">				
				<div class="form-group has-feedback">
					<label>Title</label><input name="title" type="text" class="form-control input-sm required" placeholder="Pages Title" value="" />	
					<input name="id" type="hidden" class="hidden" value="{{data.id}}" />
					<span class="glyphicon form-control-feedback"></span>
				</div>
				<div class="row">
					<div class="col-md-3">
						<div class="form-group has-feedback">
							<label>Tags</label><input name="tags" type="text" class="form-control input-sm required" placeholder="Pages Category / Tags" value="" />
							<span class="glyphicon form-control-feedback"></span>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group has-feedback">
							<label>Content Path</label><input name="contentpath" type="text" class="form-control input-sm" placeholder="Page content path" value="" />
							<span class="glyphicon form-control-feedback"></span>
						</div>
					</div>
					<div class="col-md-3">				
							<div class="form-group has-feedback">
							<label class="control-label">Publish Date</label>				
							<input name="publishdate" type="text" class="form-control input-sm datetime required" placeholder="Publish Date Time" value=""/>
							<span class="glyphicon glyphicon-calendar form-control-feedback"></span>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group has-feedback">
						<label>Status</label>
						<select name="status" class="form-control input-sm required"><option value="D">Draft</option><option value="P">Publish</option><option value="R">Remove</option></select>				
						</div>
					</div>			
				</div>
				<div class="content form-group">
					<div class="row">
						<div class="col-md-12">
							<label>Content</label>
							<div class="form-group has-feedback">
								<span class="glyphicon form-control-feedback"></span>
								<div id="pages-content" class="editor ace-form"></div>
								<textarea id="pages-content-text" name="content" class="hide required"></textarea>
							</div>					
						</div>						
					</div>
				</div>
			</div>
		</form>
		</div>
		<!-- ./end:pages-create -->

		<!-- ./photo -->
		<div class="container content photo hide">
			<form name="photo-upload" action="{{base.url}}" method="POST" target="action-catch">
			<div class="control">
				<div class="row">
					<div class="col-md-6">
						<div class="form-inline">						
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-inline text-right">
						<span class="btn btn-primary btn-file btn-sm">Browse Images <input name="files[]" type="file" multiple></span>
						<a class="btn btn-primary btn-sm save submit last" attr-action="photo.upload">Upload Photo</a>						
						</div>
					</div>
				</div>				
			</div>
			<hr>
			<div class="container-photo-list"></div>
			</form>
		</div>		
		<!-- ./end:photo -->

		<!-- ./packages -->
		<div class="container content packages hide">
			<div class="control">
				<a class="btn btn-primary btn-sm">Upload Package</a>
			</div>
			<hr>
			<div class="container-packages-list">This section is currently under development. Uploading zip packages allows quick deployment on target directories.</div>
		</div>
		<!-- ./end:packages -->

		<!-- ./templates -->
		<div class="container content templates hide">
			<form name="templates-create" action="{{base.url}}" method="POST" target="action-catch">
			<div class="control">
				<div class="row">
					<div class="col-md-6">
						<div class="form-inline">
						<a class="btn btn-primary btn-sm load-page-template">Load Page Template</a>
						<a class="btn btn-primary btn-sm load-javascript-common">Load Javascript Common</a>
						<a class="btn btn-primary btn-sm load-css-common">Load Css Common</a>										
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-inline text-right">
						<select class="form-control input-sm"><option selected>Choose Version</option></select>
						<a class="btn btn-primary btn-sm text-right">Load Version</a>				
						<a class="btn btn-primary btn-sm save submit last" attr-action="templates.create.save">Save Template</a>
						</div>
					</div>
				</div>
			</div>
			<hr>
			<div class="container-templates-list">
				<div class="form-group template-group page-template hide">
				<label>Page Template Path</label>
				<div id="page-template-content" class="editor ace-form"></div>
				<textarea id="page-template-content-text" name="page-template-content-text" class="hide"></textarea>
				</div>	
				<div class="form-group template-group javascript-common hide">
				<label>Javascript Common Path</label>
				<div id="javascript-common-content" class="editor ace-form"></div>
				<textarea id="javascript-common-content-text" name="javascript-common-content-text" class="hide"></textarea>
				</div>
				<div class="form-group template-group css-common hide">
				<label>Css Common Path</label>
				<div id="css-common-content" class="editor ace-form"></div>
				<textarea id="css-common-content-text" name="css-common-content-text" class="hide"></textarea>
				</div>
				<div class="form-group guidelines">
					<p style="word-wrap: break-word;">
					<code>{{url.base}}</code>&nbsp; <code>{{url.current}}</code>&nbsp; <code>{{url.archive}}</code>&nbsp; <code>{{url.pages}}</code>&nbsp; <code>{{html.content}}</code>&nbsp; <code>{{html.title}&#125;</code>&nbsp; <code>{{common.js}}</code>&nbsp; <code>{{common.css}}</code>&nbsp; <code>{{url.prevblog}}</code>&nbsp; <code>{{url.nextblog}}</code>&nbsp; <code>{{url.prevblog.link}}</code>&nbsp; <code>{{url.nextblog.link}}</code>&nbsp; <code>{{html.blog.title}}</code>&nbsp; <code>{{html.blog.content}}</code>&nbsp; <code>{{html.blog.publishdate}}</code>			
					</p>
					<p>
					<code>{{photo:int}}</code>&nbsp;
					</p>
				</div>
			</div>
			</form>
		</div>
		<!-- ./end:templates -->

		<!-- .deploy -->
		<div class="container content deploy hide">
			<form name="deploy-create" action="{{base.url}}" method="POST" target="action-catch">
			<div class="control">
				<div class="row">
					<div class="col-md-6">
						<div class="form-inline">
						<a class="btn btn-primary btn-sm submit" attr-action="deploy.create.save">Save Deploy Config</a>									
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-inline text-right">
						<a class="btn btn-primary btn-sm submit" attr-action="deploy.create.preview">Generate Preview</a>
						<a class="btn btn-primary btn-sm submit" attr-action="deploy.create.production">Generate Production</a>
						<a class="btn btn-primary btn-sm last submit" attr-action="deploy.create.production.photo">Generate Production Photo</a>
						</div>
					</div>
				</div>
			</div>
			<hr>
			<div class="container-deploy-list">
				<h2>Preview Configuration</h2>
				<div class="form-group">		
				<label>Preview Path</label> <input name="preview-path" type="text" class="form-control input-sm" placeholder="/www/html/<domain>/" />
				<label>Preview Url</label> <input name="preview-url" type="text" class="form-control input-sm" placeholder="//localhost/<domain>/" />		
				</div>		
				<h2>Production Configuration</h2>
				<div class="form-group">
				<label>Production Path</label> <input name="prod-path" type="text" class="form-control input-sm" placeholder="/www/prod/<domain>/" />
				<label>Production Url</label> <input name="prod-url" type="text" class="form-control input-sm" placeholder="//<domain>/" />
				</div>
			</div>
			</form>
		</div>
		<!-- .end:deploy -->
	</div>
	
	<iframe name="action-catch" class="action catch hide"></iframe>

	<!-- Modal Loading -->
	<div class="modal fade" id="modal-loading" role="dialog">
		<div class="modal-dialog">	
			<div class="modal-content">
				<div class="modal-body text-center">
					<div class="progress">
						<div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100" style="width:20%">Loading&nbsp;<span>20</span>%</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal Edit Photo -->
	<div class="modal fade" id="modal-edit-photo" role="dialog">		
		<form name="photo-reupload" action="{{base.url}}" method="POST" target="action-catch">
		<div class="modal-dialog">	
			<div class="modal-content">
				<div class="modal-body text-center">
					<div><img src="" class="img-photo img-responsive" /></div>
					<div class="img-container text-left"><div class="info"></div></div>
				</div>
				<div class="modal-footer">	
					<div class="row">
						<div class="col-md-6 text-left">
							<input name="photoid" type="hidden" />
							<span class="btn btn-primary btn-file btn-sm" style="margin-bottom:0px;">Choose Photo<input name="files[]" type="file"></span>
							<a class="btn btn-primary btn-sm save submit" attr-action="photo.reupload" data-dismiss="modal">Reupload Photo</a>
						</div>
						<div class="col-md-6 text-right">							
							<button type="button" class="btn btn-primary btn-sm" data-dismiss="modal">Close Image</button>
						</div>
					</div>
				</div>
			</div>			
		</div>
		</form>
	</div>

</body>
</html>	
EOF;

}

?>
