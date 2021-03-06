<?php
/**
 * Kunena Component
 * @package Kunena.Framework
 * @subpackage User
 *
 * @copyright (C) 2008 - 2012 Kunena Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 **/
defined ( '_JEXEC' ) or die ();

jimport ( 'joomla.utilities.date' );
jimport ( 'joomla.filesystem.file' );

/**
 * Kunena User Class
 */
class KunenaUser extends JObject {
	// Global for every instance
	protected static $_ranks = null;
	protected $_type = false;
	protected $_class = false;
	protected $_allowed = array();
	protected $_link = array();

	protected $_exists = false;
	protected $_db = null;

	/**
	 * Constructor
	 *
	 * @access	protected
	 */
	public function __construct($identifier = 0) {
		// Always load the user -- if user does not exist: fill empty data
		if ($identifier !== false) $this->load ( $identifier );
		$this->_db = JFactory::getDBO ();
		$this->_app = JFactory::getApplication ();
		$this->_config = KunenaFactory::getConfig ();
	}

	/**
	 * Returns the global KunenaUser object, only creating it if it doesn't already exist.
	 *
	 * @access	public
	 * @param	int	$id	The user to load - Can be an integer or string - If string, it is converted to ID automatically.
	 * @return	JUser			The User object.
	 * @since	1.6
	 */
	public static function getInstance($identifier = null, $reload = false) {
		return KunenaUserHelper::get($identifier, $reload);
	}

	public function exists($exists = null) {
		$return = $this->_exists;
		if ($exists !== null) $this->_exists = $exists;
		return $return;
	}

	public function isMyself() {
		static $result = null;
		if ($result === null) $result = KunenaUserHelper::getMyself()->userid == $this->userid;
		return $result;
	}

	/**
	 * Method to get the user table object
	 *
	 * This function uses a static variable to store the table name of the user table to
	 * it instantiates. You can call this function statically to set the table name if
	 * needed.
	 *
	 * @access	public
	 * @param	string	The user table name to be used
	 * @param	string	The user table prefix to be used
	 * @return	object	The user table object
	 * @since	1.6
	 */
	public function getTable($type = 'KunenaUsers', $prefix = 'Table') {
		static $tabletype = null;

		//Set a custom table type is defined
		if ($tabletype === null || $type != $tabletype ['name'] || $prefix != $tabletype ['prefix']) {
			$tabletype ['name'] = $type;
			$tabletype ['prefix'] = $prefix;
		}

		// Create the user table object
		return JTable::getInstance ( $tabletype ['name'], $tabletype ['prefix'] );
	}

	public function bind($data, $ignore = array()) {
		$data = array_diff_key($data, array_flip($ignore));
		$this->setProperties ( $data );
	}

	/**
	 * Method to load a KunenaUser object by userid
	 *
	 * @access	public
	 * @param	mixed	$identifier The user id of the user to load
	 * @param	string	$path		Path to a parameters xml file
	 * @return	boolean			True on success
	 * @since 1.6
	 */
	public function load($id) {
		// Create the user table object
		$table = $this->getTable ();

		// Load the KunenaTableUser object based on the user id
		$this->_exists = $table->load ( $id );

		// Assuming all is well at this point lets bind the data
		$this->setProperties ( $table->getProperties () );

		// Set showOnline if user doesn't exists (if we will save the user)
		if (!$this->_exists) $this->showOnline = 1;

		return $this->_exists;
	}

	/**
	 * Method to save the KunenaUser object to the database
	 *
	 * @access	public
	 * @param	boolean $updateOnly Save the object only if not a new user
	 * @return	boolean True on success
	 * @since 1.6
	 */
	public function save($updateOnly = false) {
		// Create the user table object
		$table = $this->getTable ();
		$ignore = array('name', 'username', 'email', 'blocked', 'registerDate', 'lastvisitDate');
		$table->bind ( $this->getProperties (), $ignore );
		$table->exists ( $this->_exists );

		// Check and store the object.
		if (! $table->check ()) {
			$this->setError ( $table->getError () );
			return false;
		}

		//are we creating a new user
		$isnew = ! $this->_exists;

		// If we aren't allowed to create new users return
		if (! $this->userid || ($isnew && $updateOnly)) {
			return true;
		}

		//Store the user data in the database
		if (! $result = $table->store ()) {
			$this->setError ( $table->getError () );
		}

		$access = KunenaAccess::getInstance();
		$access->clearCache();

		// Set the id for the KunenaUser object in case we created a new user.
		if ($result && $isnew) {
			$this->load ( $table->get ( 'userid' ) );
			//self::$_instances [$table->get ( 'id' )] = $this;
		}

		return $result;
	}

	/**
	 * Method to delete the KunenaUser object from the database
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.6
	 */
	public function delete() {
		// Delete user table object
		$table = $this->getTable ();

		$result = $table->delete ( $this->userid );
		if (! $result) {
			$this->setError ( $table->getError () );
		}

		$access = KunenaAccess::getInstance();
		$access->clearCache();

		return $result;

	}

	public function isOnline($yes = false, $no = 'offline') {
		return KunenaUserHelper::isOnline($this->userid, $yes, $no);
	}

	public function getAllowedCategories($rule = 'read') {
		if (!isset($this->_allowed[$rule])) {
			$acl = KunenaAccess::getInstance();
			$allowed = $acl->getAllowedCategories ( $this->userid, $rule );
			$this->_allowed[$rule] = $allowed;
		}
		return $this->_allowed[$rule];
	}

	public function getMessageOrdering() {
		static $ordering = null;
		if (is_null($ordering)) {
			if ($this->ordering != '0') {
				$ordering = $this->ordering == '1' ? 'desc' : 'asc';
			} else {
				$ordering = KunenaFactory::getConfig()->get('default_sort') == 'desc' ? 'desc' : 'asc';
			}
			if ($ordering != 'asc') {
				$ordering = 'desc';
			}
		}
		return $ordering;
	}

	/**
	 * Checks if user has administrator permissions in the category.
	 *
	 * If no category is given or it doesn't exist, check will be done against global administrator permissions.
	 *
	 * @param KunenaForumCategory $category
	 * @return bool
	 *
	 * @since 2.0.0-BETA2
	 */
	public function isAdmin(KunenaForumCategory $category = null) {
		return KunenaAccess::getInstance()->isAdmin ( $this, $category && $category->exists() ? $category->id : null );
	}

	/**
	 * Checks if user has moderator permissions in the category.
	 *
	 * If no category is given or it doesn't exist, check will be done against global moderator permissions.
	 *
	 * @param KunenaForumCategory $category
	 * @return bool
	 *
	 * @since 2.0.0-BETA2
	 */
	public function isModerator(KunenaForumCategory $category = null) {
		return KunenaAccess::getInstance()->isModerator ( $this, $category && $category->exists() ? $category->id : null );
	}

	public function isBanned() {
		if (! $this->banned)
			return false;
		if ($this->blocked || $this->banned == $this->_db->getNullDate ())
			return true;

		$ban = new JDate ( $this->banned );
		$now = new JDate ();
		return ($ban->toUnix () > $now->toUnix ());
	}

	public function isBlocked() {
		if ($this->blocked)
			return true;
		return false;
	}

	public function getName($visitorname = '', $escape = true) {
		if (! $this->userid && !$this->name) {
			$name = $visitorname;
		} else {
			$name = $this->_config->username ? $this->username : $this->name;
		}
		if ($escape) $name = htmlspecialchars($name, ENT_COMPAT, 'UTF-8');
		return $name;
	}

	public function getAvatarImage($class = '', $sizex = 'thumb', $sizey = 90) {
		$avatars = KunenaFactory::getAvatarIntegration ();
		return $avatars->getLink ( $this, $class, $sizex, $sizey );
	}

	public function getAvatarURL($sizex = 'thumb', $sizey = 90) {
		$avatars = KunenaFactory::getAvatarIntegration ();
		return $avatars->getURL ( $this, $sizex, $sizey );
	}

	public function getLink($name = null, $title = null, $rel = 'nofollow', $task = '') {
		if (!$name) {
			$name = $this->getName();
		}
		$key = "{$name}.{$title}.{$rel}";
		if (empty($this->_link[$key])) {
			if (!$title) {
				$title = JText::sprintf('COM_KUNENA_VIEW_USER_LINK_TITLE', $this->getName());
			}
			$uclass = $this->getType(0, 'class');
			$link = $this->getURL (true, $task);
			if (! empty ( $link ))
				$this->_link[$key] = "<a class=\"{$uclass}\" href=\"{$link}\" title=\"{$title}\" rel=\"{$rel}\">{$name}</a>";
			else
				$this->_link[$key] = "<span class=\"{$uclass}\">{$name}</span>";
		}
		return $this->_link[$key];
	}

	public function getURL($xhtml = true, $task = '') {
		if (!$this->exists()) return;
		return KunenaFactory::getProfile ()->getProfileURL ( $this->userid, $task, $xhtml );
	}

	public function getType($catid = 0, $code=false) {
		static $types = array(
			'admin'=>'COM_KUNENA_VIEW_ADMIN',
			'globalmod'=>'COM_KUNENA_VIEW_GLOBAL_MODERATOR',
			'moderator'=>'COM_KUNENA_VIEW_MODERATOR',
			'user'=>'COM_KUNENA_VIEW_USER',
			'guest'=>'COM_KUNENA_VIEW_VISITOR',
			'banned'=>'COM_KUNENA_VIEW_BANNED',
			'blocked'=>'COM_KUNENA_VIEW_BANNED'
		);
		$moderatedCategories = KunenaAccess::getInstance()->getModeratorStatus($this);
		if (!$this->_type) {
			if ($this->userid == 0) {
				$this->_type = 'guest';
			} elseif ($this->isBanned ()) {
				$this->_type = 'banned';
			} elseif ($this->isAdmin ( KunenaForumCategoryHelper::get($catid) )) {
				$this->_type = 'admin';
			} elseif ($this->isModerator ( null )) {
				$this->_type = 'globalmod';
			} elseif (!$catid && !empty($moderatedCategories)) {
				$this->_type = 'moderator';
			} elseif ($catid && isset($moderatedCategories[$catid])) {
				$this->_type = 'moderator';
			} else {
				$this->_type = 'user';
			}
			$userClasses = KunenaFactory::getTemplate()->getUserClasses();
			$this->_class = isset($userClasses[$this->_type]) ? $userClasses[$this->_type] : $userClasses[0].$this->_type;
		}

		return $code == 'class' ? $this->_class : ($code == false ? $types[$this->_type] : $this->_type);
	}
	public function getRank($catid = 0, $type = false) {
		// Default rank
		$rank = new stdClass ();
		$rank->rank_id = false;
		$rank->rank_title = null;
		$rank->rank_min = 0;
		$rank->rank_special = 0;
		$rank->rank_image = null;

		$config = KunenaFactory::getConfig ();
		$category = KunenaForumCategoryHelper::get($catid);

		if (! $config->showranking)
			return;
		if (self::$_ranks === null) {
			$this->_db->setQuery ( "SELECT * FROM #__kunena_ranks" );
			self::$_ranks = $this->_db->loadObjectList ( 'rank_id' );
			KunenaError::checkDatabaseError ();
		}

		$rank->rank_title = JText::_ ( 'COM_KUNENA_RANK_USER' );
		$rank->rank_image = 'rank0.gif';

		if ($this->userid == 0) {
			$rank->rank_id = 0;
			$rank->rank_title = JText::_ ( 'COM_KUNENA_RANK_VISITOR' );
			$rank->rank_special = 1;
		} else if ($this->isBanned ()) {
			$rank->rank_id = 0;
			$rank->rank_title = JText::_ ( 'COM_KUNENA_RANK_BANNED' );
			$rank->rank_special = 1;
			$rank->rank_image = 'rankbanned.gif';
			foreach ( self::$_ranks as $cur ) {
				if ($cur->rank_special == 1 && JFile::stripExt ( $cur->rank_image ) == 'rankbanned') {
					$rank = $cur;
					break;
				}
			}
		} else if ($this->rank != 0 && isset ( self::$_ranks [$this->rank] )) {
			$rank = self::$_ranks [$this->rank];
		} else if ($this->rank == 0 && $this->isAdmin ( $category )) {
			$rank->rank_id = 0;
			$rank->rank_title = JText::_ ( 'COM_KUNENA_RANK_ADMINISTRATOR' );
			$rank->rank_special = 1;
			$rank->rank_image = 'rankadmin.gif';
			foreach ( self::$_ranks as $cur ) {
				if ($cur->rank_special == 1 && JFile::stripExt ( $cur->rank_image ) == 'rankadmin') {
					$rank = $cur;
					break;
				}
			}
		} else if ($this->rank == 0 && $this->isModerator ( $category )) {
			$rank->rank_id = 0;
			$rank->rank_title = JText::_ ( 'COM_KUNENA_RANK_MODERATOR' );
			$rank->rank_special = 1;
			$rank->rank_image = 'rankmod.gif';
			foreach ( self::$_ranks as $cur ) {
				if ($cur->rank_special == 1 && JFile::stripExt ( $cur->rank_image ) == 'rankmod') {
					$rank = $cur;
					break;
				}
			}
		}
		if ($rank->rank_id === false) {
			//post count rank
			$rank->rank_id = 0;
			foreach ( self::$_ranks as $cur ) {
				if ($cur->rank_special == 0 && $cur->rank_min <= $this->posts && $cur->rank_min >= $rank->rank_min) {
					$rank = $cur;
				}
			}
		}
		if ($type == 'title') {
			return $rank->rank_title;
		}
		if ($type == 'image') {
			$template = KunenaTemplate::getInstance();
			if (! $config->rankimages)
				return;
			$iconurl = $template->getRankPath($rank->rank_image, true);
			return '<img src="' . $iconurl . '" alt="" />';
		}
		if (! $config->rankimages) {
			$rank->rank_image = null;
		}
		return $rank;
	}

	public function getTopicLayout( $layout = null ) {
		if ($layout == 'default') $layout = null;
		if (!$layout) $layout = $this->_app->getUserState ( 'com_kunena.topic_layout' );
		if (!$layout) $layout = $this->view;

		switch ( $layout ) {
			case 'flat':
			case 'threaded':
			case 'indented':
				break;
			default:
				$layout = $this->_config->topic_layout;
		}

		return $layout;
	}

	public function setTopicLayout( $layout = 'default' ) {
		if ($layout != 'default') $layout = $this->getTopicLayout( $layout );

		$this->_app->setUserState ( 'com_kunena.topic_layout', $layout );

		if ($this->userid) {
			$this->view = $layout;
			$this->save(true);
		}
	}

	public function profileIcon($name) {
		switch ($name) {
			case 'gender' :
				switch ($this->gender) {
					case 1 :
						$gender = 'male';
						break;
					case 2 :
						$gender = 'female';
						break;
					default :
						$gender = 'unknown';
				}
				$title = JText::_ ( 'COM_KUNENA_MYPROFILE_GENDER' ) . ': ' . JText::_ ( 'COM_KUNENA_MYPROFILE_GENDER_' . $gender );
				return '<span class="kicon-profile kicon-profile-gender-' . $gender . '" title="' . $title . '"></span>';
				break;
			case 'birthdate' :
				if ($this->birthdate) {
					$date = new JDate ( $this->birthdate );
					if ($date->format('%Y')<1902) break;
					return '<span class="kicon-profile kicon-profile-birthdate" title="' . JText::_ ( 'COM_KUNENA_MYPROFILE_BIRTHDATE' ) . ': ' . KunenaDate::getInstance($this->birthdate)->toKunena( 'date', 0 ) . '"></span>';
				}
				break;
			case 'location' :
				if ($this->location)
					return '<span class="kicon-profile kicon-profile-location" title="' . JText::_ ( 'COM_KUNENA_MYPROFILE_LOCATION' ) . ': ' . $this->escape ( $this->location ) . '"></span>';
				break;
			case 'website' :
				$url = 'http://' . $this->websiteurl;
				if (! $this->websitename)
					$websitename = $this->websiteurl;
				else
					$websitename = $this->websitename;
				if ($this->websiteurl)
					return '<a href="' . $this->escape ( $url ) . '" target="_blank"><span class="kicon-profile kicon-profile-website" title="' . JText::_ ( 'COM_KUNENA_MYPROFILE_WEBSITE' ) . ': ' . $this->escape ( $websitename ) . '"></span></a>';
				break;
			case 'private' :
				$pms = KunenaFactory::getPrivateMessaging ();
				return $pms->showIcon ( $this->userid );
				break;
			case 'email' :
				// TODO: show email
				return; // '<span class="email" title="'. JText::_('COM_KUNENA_MYPROFILE_EMAIL').'"></span>';
				break;
			case 'profile' :
				if (! $this->userid)
					return;
				return $this->getLink('<span class="profile" title="' . JText::_ ( 'COM_KUNENA_VIEW_PROFILE' ) . '"></span>');
				break;
		}
	}

	public function socialButton($name, $gray = false) {
		$social = array ('twitter' => array ('url' => 'http://twitter.com/##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_TWITTER' ), 'nourl' => '0' ),
			'facebook' => array ('url' => 'http://www.facebook.com/##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_FACEBOOK' ), 'nourl' => '0' ),
			'myspace' => array ('url' => 'http://www.myspace.com/##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_MYSPACE' ), 'nourl' => '0' ),
			'linkedin' => array ('url' => 'http://www.linkedin.com/in/##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_LINKEDIN' ), 'nourl' => '0' ),
			'delicious' => array ('url' => 'http://delicious.com/##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_DELICIOUS' ), 'nourl' => '0' ),
			'friendfeed' => array ('url' => 'http://friendfeed.com/##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_FRIENDFEED' ), 'nourl' => '0' ),
			'digg' => array ('url' => 'http://www.digg.com/users/##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_DIGG' ), 'nourl' => '0' ),
			'skype' => array ('url' => '##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_SKYPE' ), 'nourl' => '1' ),
			'yim' => array ('url' => '##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_YIM' ), 'nourl' => '1' ),
			'aim' => array ('url' => '##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_AIM' ), 'nourl' => '1' ),
			'gtalk' => array ('url' => '##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_GTALK' ), 'nourl' => '1' ),
			'msn' => array ('url' => '##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_MSN' ), 'nourl' => '1' ),
			'icq' => array ('url' => 'http://www.icq.com/people/cmd.php?uin=##VALUE##&action=message', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_ICQ' ), 'nourl' => '0' ),
			'blogspot' => array ('url' => 'http://##VALUE##.blogspot.com/', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_BLOGSPOT' ), 'nourl' => '0' ),
			'flickr' => array ('url' => 'http://www.flickr.com/photos/##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_FLICKR' ), 'nourl' => '0' ),
			'bebo' => array ('url' => 'http://www.bebo.com/Profile.jsp?MemberId=##VALUE##', 'title' => JText::_ ( 'COM_KUNENA_MYPROFILE_BEBO' ), 'nourl' => '0' )
		);
		if (! isset ( $social [$name] ))
			return;
		$title = $social [$name] ['title'];
		$value = $this->escape ( $this->$name );
		$url = strtr ( $social [$name] ['url'], array ('##VALUE##' => $value ) );
		if ($social [$name] ['nourl'] == '0') {
			if (! empty ( $this->$name ))
				return '<a href="' . $this->escape ( $url ) . '" class="kTip" target="_blank" title="' . $title . ': ' . $value . '"><span class="kicon-profile kicon-profile-' . $name . '"></span></a>';
		} else {
			if (! empty ( $this->$name ))
				return '<span class="kicon-profile kicon-profile-' . $name . ' kTip" title="' . $title . ': ' . $value . '"></span>';
		}
		if ($gray)
			return '<span class="kicon-profile kicon-profile-' . $name . '-off"></span>';
		else
			return '';
	}

	public function escape($var) {
		return htmlspecialchars($var, ENT_COMPAT, 'UTF-8');
	}
}
