<?php
/**************************************************************************\
* eGroupWare - KnowledgeBase                                               *
* http://www.egroupware.org                                                *
* Written by Alejandro Pedraza [alejandro.pedraza AT dataenlace DOT com]   *
* ------------------------------------------------------------------------ *
*  Started off as a port of phpBrain - http://vrotvrot.com/phpBrain/	   *
*  but quickly became a full rewrite					                   *
* ------------------------------------------------------------------------ *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

	/**
	* @class bokb
	*
	* @abstract		Business rules layer of the Knowledge Base
	* @Last Editor	$ Author: alpeb $
	* @author		Alejandro Pedraza
	* @version		$ Revision: 0.99 $
	* @license		GPL
	**/
	class bokb
	{
		var $so;
		var $categories_obj;
		var $all_categories;
		var $categories;
		var $grants;
		var $preferences;
		var $start;
		var $sort;
		var $order;
		var $admin_config;
		var $num_rows;
		var $num_questions;
		var $num_comments;
		var $error_msg;
		var $publish_filter;
		var $query;
		var $article_owner;
		var $article_id;
		var $messages_array = array(
			'no_perm'				=> 'You have not the proper permissions to do that',
			'add_ok_cont'			=> 'Article added to database, you can now attach files or links, or relate to other articles',
			'comm_submited'			=> 'Comment has been submited for revision',
			'comm_ok'				=> 'Comment has been published',
			'rate_ok'				=> 'Rating has been submited',
			'comm_rate_ok'			=> 'Comment and rating have been published',
			'comm_rate_submited'	=> 'Comment has been submited for revision and rating will be published',
			'no_basedir'			=> 'Base directory does not exist, please ask adminstrator to check the global configuration',
			'no_kbdir'				=> '/kb directory does not exist and could not be created, please ask adminstrator to check the global configuration',
			'overwrite'				=> 'That file already exists',
			'no_file_serv'			=> 'The file was already missing in the server',
			'failure_delete'		=> 'Failure trying to delete the file',
			'file_del_ok'			=> 'File was deleted successfully',
			'file_db_del_err'		=> 'File could be deleted from server but not from database',
			'file_noserv_db_ok'		=> "File was already missing from server, and was deleted from the database",
			'file_noserv_db_err'	=> "File wasn't in server and it couldn't be deleted from the database",
			'del_rel_ok'			=> 'Relation with article was removed successfully',
			'link_del_err'			=> 'Error deleting link',
			'link_del_ok'			=> 'Link deleted successfully',
			'error_cd'				=> 'Error locating files directory',
			'nothing_uploaded'		=> 'Nothing was uploaded!',
			'error_cp'				=> 'Error moving file to directory',
			'upload_ok'				=> 'File has been successfully uploaded',
			'articles_added'		=> 'Articles added',
			'articles_not_added'	=> 'Problem relating articles',
			'link_ok'				=> 'Link has been added',
			'link_prob'				=> 'Link could not be added',
			'err_del_art'			=> 'Error deleting article from database',
			'err_del_q'				=> 'Error trying to delete question',
			'del_art_ok'			=> 'Article deleted successfully',
			'del_arts_ok'			=> 'Articles deleted successfully',
			'del_q_ok'				=> 'Question deleted successfully',
			'del_qs_ok'				=> 'Questions deleted successfully',
			'del_comm_err'			=> 'Error trying to delete comment',
			'del_comm_ok'			=> 'Comment has been deleted',
			'edit_err'				=> 'Error trying to edit article',
			'publish_err'			=> 'Error trying to publish article',
			'publish_comm_err'		=> 'Error publishing comment',
			'publish_ok'			=> 'Article has been published',
			'publish_comm_ok'		=> 'Comment has been pusblished',
			'publishs_ok'			=> 'Articles have been published',
			'mail_ok'				=> 'e-mail has been sent'
		);


		function bokb()
		{
			// version check
			if ($GLOBALS['phpgw_info']['apps']['phpbrain']['version'] == '0.9.14.001')
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();
				die("Please upgrade this application to be able to use it");
			}

			$this->so						= CreateObject('phpbrain.sokb');
			$this->categories_obj			= CreateObject('phpgwapi.categories', '', 'phpbrain');	// force phpbrain cause it might be running from sitemgr...
			$GLOBALS['phpgw']->config		= CreateObject('phpgwapi.config');
			$GLOBALS['phpgw']->vfs			= CreateObject('phpgwapi.vfs');
			$GLOBALS['phpgw']->historylog	= CreateObject('phpgwapi.historylog','phpbrain');

			$this->grants				= $GLOBALS['phpgw']->acl->get_grants('phpbrain');
			$this->preferences			= $GLOBALS['phpgw']->preferences->data['phpbrain'];
			
			$this->read_right			= PHPGW_ACL_READ;
			$this->edit_right			= PHPGW_ACL_EDIT;
			$this->publish_right		= PHPGW_ACL_CUSTOM_1;

			$this->admin_config		= $GLOBALS['phpgw']->config->read_repository();

			if (!$this->all_categories = $this->categories_obj->return_sorted_array('', False, '', '', '', True, 0)) $this->all_categories = array();

			// default preferences and admin config
			if (!$this->preferences['num_lines']) $this->preferences['num_lines'] = 3;
			if (!$this->preferences['show_tree']) $this->preferences['show_tree'] = 'all';
			if (!$this->preferences['num_comments']) $this->preferences['num_comments'] = '5';
			if (!$this->admin_config['publish_comments']) $this->admin_config['publish_comments'] = 'True';
			if (!$this->admin_config['publish_articles']) $this->admin_config['publish_articles'] = 'True';
			if (!$this->admin_config['publish_questions']) $this->admin_config['publish_questions'] = 'True';
			
			$this->start			= get_var('start', 'any', 0);
			$this->query			= urldecode(get_var('query', 'any', ''));
			$this->sort				= get_var('sort', 'any', '');
			$this->order			= get_var('order', 'any', '');
			$this->publish_filter	= get_var('publish_filter', 'any', 'all');

			// advanced search parameters
			$this->all_words	= get_var('all_words', 'any', '');
			$this->phrase		= get_var('phrase', 'any', '');
			$this->one_word		= get_var('one_word', 'any', '');
			$this->without_words= get_var('without_words', 'any', '');
			$this->cat			= get_var('cat', 'any', 0);
			$this->include_subs	= get_var('include_subs', 'any', '');
			$this->pub_date		= get_var('pub_date', 'any', '');
			$this->ocurrences	= get_var('ocurrences', 'any', 0);
			$this->num_res		= get_var('num_res', 'any', '');
		}

		function return_single_category($cat_id)
		{
			return $this->categories_obj->return_single($cat_id);
		}

		/**
		* @function	load_categories 
		*
		* @abstract	Loads in object $this->categories, an array of the descendant categories of $parent_cat_id
		* @author	Alejandro Pedraza
		* @params	$parent_cat_id	int	id of the parent category
		**/
		function load_categories($parent_cat_id)
		{
			if (!$this->categories = $this->categories_obj->return_sorted_array('', False, '', '', '', True, $parent_cat_id)) $this->categories = array();
		}

		function select_category($category_selected = '')
		{
			return $this->categories_obj->formated_list('select', 'all', $category_selected , True);
		}

		function accessible_owners($permissions = 0)
		{
			$owners = array($GLOBALS['phpgw_info']['user']['account_id']);
			if (!$permissions) $permissions = $this->read_right;
			foreach ($this->grants as $user=>$right)
			{
				if ($right & $permissions)
				{
					$owners[] = $user;
				}
			}

			return $owners;
		}

		/**
		* @function check_permission
		*
		* @abstract	Checks for rights on article
		* @author	Alejandro Pedraza
		* @params	$check_rights	bitmask ACL right (use $this->read_right or $this->edit_right)
		* @params	$article_owner	if not set, checks rights against current article
		* @returns	True if has rights, False if not
		**/
		function check_permission($check_rights, $article_owner = 0)
		{
			if (!$article_owner) $article_owner = $this->article_owner;
			if ($this->grants[$article_owner])
			{
				$rights_on_owner = $this->grants[$article_owner];
			}
			else
			{
				return False;
			}

			return ($rights_on_owner & $check_rights);
		}

		/**
		* @function	search_articles
		*
		* @abstract Returns array of articles
		* @author	Alejandro Pedraza
		* @params	$category_id	Category under which articles are to be retrieved
		* @params	$publish_filter	Filter by published or unpublished
		* @params	$permissions	Specific permissions on article owners
		* @returns	Array with articles
		**/
		function search_articles($category_id, $publish_filter = False, $permissions=0, $questions=False)
		{
			$search = $questions? 'unanswered_questions' : 'search_articles';
			if (!$permissions) $permissions = $this->read_right;

			$owners = $this->accessible_owners($permissions);
			
			if ($this->preferences['show_tree'] == 'all')
			{
				// show all articles under present category and descendant categories
				$cats_ids = array();
				foreach ($this->categories as $cat)
				{
					$cats_ids[] = $cat['id'];
				}
				$cats_ids[] = $category_id;

				$articles = $this->so->$search($owners, $cats_ids, $this->start, '', $this->sort, $this->order, $publish_filter, $this->query);
			}
			elseif ($category_id == 0)
			{
				// show only articles that are not categorized
				$articles = $this->so->$search($owners, 0, $this->start, '', $this->sort, $this->order, $publish_filter, $this->query);
			}
			else
			{
				// show only articles in present category
				$articles = $this->so->$search($owners, array($category_id), '', '', $this->sort, $this->order, $publish_filter, $this->query);
			}

			$this->num_rows = $this->so->num_rows;
			return $articles;
		}

		function adv_search_articles()
		{
			$owners = $this->accessible_owners();

			$cats_ids = array();
			if ($this->cat && !$this->include_subs)
			{
				// only search in one category
				$cats_ids[] = $this->cat;
			}
			elseif ($this->cat)
			{
				// search in category passed and all its descendency
				foreach ($this->categories as $cat)
				{
					$cats_ids[] = $cat['id'];
				}
				$cats_ids[] = $this->cat;
			}

			$articles = $this->so->adv_search_articles($owners, $cats_ids, $this->ocurrences, $this->pub_date, $this->start, $this->num_res, $this->all_words, $this->phrase, $this->one_word, $this->without_words, $this->cat, $this->include_subs, $this->pub_date);
			$this->num_rows = $this->so->num_rows;
			return $articles;
		}

		function unanswered_questions($category_id)
		{
			$owners = $this->accessible_owners();
			
			$cats_ids = array();
			foreach ($this->categories as $cat)
			{
				$cats_ids[] = $cat['id'];
			}
			$cats_ids[] = $category_id;

			$questions = $this->so->unanswered_questions($owners, $cats_ids, 0, $this->preferences['num_lines'], 'DESC', 'creation', 'published', '');
			$this->num_questions = $this->so->num_questions;
			return $questions;
		}

		function return_history()
		{
			$history = $GLOBALS['phpgw']->historylog->return_array('', '', 'history_timestamp', 'DESC', $this->article_id);
			// echo "history: <pre>";print_r($history);echo "</pre>";
			for ($i = 0; $i<sizeof($history); $i++)
			{
				$history[$i]['datetime'] = $GLOBALS['phpgw']->common->show_date($history[$i]['datetime'], $GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
				$GLOBALS['phpgw']->accounts->get_account_name($history[$i]['owner'], $lid, $fname, $lname);
				$history[$i]['owner'] = $fname . ' ' . $lname;

				switch ($history[$i]['status'])
				{
					case 'AF':
						$history[$i]['action'] = lang ('Added file %1', $history[$i]['new_value']);
						break;
					case 'RF':
						$history[$i]['action'] = lang ('Removed file %1', $history[$i]['new_value']);
						break;
					case 'AL':
						$history[$i]['action'] = lang ('Added link %1', $history[$i]['new_value']);
						break;
					case 'RL':
						$history[$i]['action'] = lang ('Removed link %1', $history[$i]['new_value']);
						break;
					case 'AR':
						$history[$i]['action'] = lang ('Added related articles %1', $history[$i]['new_value']);
						break;
					case 'DR':
						$history[$i]['action'] = lang ('Deleted relation to  article %1', $history[$i]['new_value']);
						break;
					case 'EA':
						$history[$i]['action'] = lang ('Article edited');
						break;
					case 'NA':
						$history[$i]['action'] = lang ('Article created');
						break;
					case 'AD':
						$history[$i]['action'] = lang ('Article deleted');
						break;
				}
			}
			return $history;
		}

		/**
		* @function	return_latest_mostviewed
		*
		* @abstract	Returns latest or most viewed articles
		* @author	Alejandro Pedraza
		* @params	$category_id	int	articles must belong to the descendancy of this category
		* @params	$order	Field by which the query is ordered, determines whether latest or most viewed articles are returned
		* @returns	array of articles
		**/
		function return_latest_mostviewed($category_id = 0, $order = '')
		{
			$owners = $this->accessible_owners();

			$cats_ids = array($category_id);
			foreach ($this->categories as $cat)
			{
				$cats_ids[] = $cat['id'];
			}

			return $this->so->search_articles($owners, $cats_ids, 0, $this->preferences['num_lines'], 'DESC', $order, 'published', '');
		}

		/**
		* @function	save_article
		*
		* @abstract	Saves new or edited article
		* @author	Alejandro Pedraza
		* @params	$content (optional)
		* @returns	True on success, False on failure
		**/
		function save_article($content = '')
		{
			if (!$content) $content = $_POST;

			$content['text'] = $content['exec']['text'];

			// if editing an article, check it has the right to do so
			if ($content['editing_article_id'] && !($this->check_permission($this->edit_right)))
			{
				$this->error_msg = lang('You have not the proper permissions to do that');
				return False;
			}
			elseif ($content['editing_article_id'])
			{
				if(!$art_id = $this->so->save_article($content, False))
				{
					$this->error_msg = 'edit_err';
					return False;
				}
				$GLOBALS['phpgw']->historylog->add('EA', $this->article_id, 'article edited', '');
				return $art_id;
			}

			// if adding a new article, check that the  articleID doesn't already exist if it was given
			if ($content['articleID'] && $this->so->exist_articleID($content['articleID']))
			{
				$this->error_msg = "Article ID already exists";
				return False;
			}

			$publish = False;
			if ($this->admin_config['publish_articles'] == 'True') $publish = True;

			$art_id = $this->so->save_article($content, True, $publish);
			if ($art_id) $GLOBALS['phpgw']->historylog->add('NA', $art_id, 'article created', '');
			return $art_id;
		}

		function delete_article($files, $art_id = 0, $owner = 0)
		{
			if (!$art_id) $art_id = $this->article_id;
			// check user has edit rights
			if (!$this->check_permission($this->edit_right, $owner)) return 'no_perm';
			// delete files
			if ($files)
			{
				foreach ($files as $file)
				{
					// verify the file exists in the server
					$test = $GLOBALS['phpgw']->vfs->ls(array(
						'string'		=> '/kb/' . $file['file'],
						'relatives'		=> array(RELATIVE_NONE),
						'checksubdirs'	=> False,
						'nofiles'		=> False
					));
					if ($test[0]['name'])
					{
						// the file is in the server, proceed to rm it
						$remove = $GLOBALS['phpgw']->vfs->rm(array(
							'string'	=> '/kb/' . $file['file'],
							'relatives'	=> array(RELATIVE_NONE)
						));
					}
				}
			}
			// delete comments
			$this->so->delete_comments($art_id);
			// delete ratings
			$this->so->delete_ratings($art_id);
			// delete related articles
			$this->so->delete_related($art_id, $art_id, True);
			// delete search index
			$this->so->delete_search($art_id);
			// delete article
			if (!$this->so->delete_article($art_id)) return 'err_del_art';
			if ($art_id) $GLOBALS['phpgw']->historylog->add('AD', $art_id, 'article deleted', '');
			return 'del_art_ok';
		}

		function delete_question($q_id, $owner)
		{
			// check user has edit rights on owner
			if (!$this->check_permission($this->edit_right, $owner)) return 'no_perm';

			if (!$this->so->delete_question($q_id)) return 'err_del_q';
			return 'del_q_ok';
		}

		function get_article($art_id)
		{
			if (!$article = $this->so->get_article($art_id)) return False;
			$this->article_id = $article['art_id'];

			// check permissions
			$this->article_owner = $article['user_id'];
			if (!$this->check_permission($this->read_right)) $this->die_peacefully('You have not the proper permissions to do that');

			$GLOBALS['phpgw']->accounts->get_account_name($article['user_id'], $lid, $fname, $lname);
			$article['username'] = $fname . ' ' . $lname;
			$fname = ''; $lname = '';
			$GLOBALS['phpgw']->accounts->get_account_name($article['modified_user_id'], $lid, $fname, $lname);
			$article['modified_username'] = $fname . ' ' .$lname;

			// register article view if it has been published (one hit per session)
			if (!$data = $GLOBALS['phpgw']->session->appsession('views', 'phpbrain')) $data = array();
			if ($article['published'] && !in_array($this->article_id, $data))
			{
				$data[] = $this->article_id;
				$GLOBALS['phpgw']->session->appsession('views', 'phpbrain', $data);
				$this->so->register_view($this->article_id, $article['views']);
			}

			// process search_feedback (can do this only once per session per article)
			if (!$data = $GLOBALS['phpgw']->session->appsession('feedback', 'phpbrain')) $data = array();
			if ($_POST['feedback_query'] && !in_array($this->article_id, $data))
			{
				$data[] = $this->article_id;
				$GLOBALS['phpgw']->session->appsession('feedback', 'phpbrain', $data);
				$upgrade_key = $_POST['yes_easy']? True : False;
				$fields = array('title', 'topic', 'text');
				$words = explode(' ', $_POST['feedback_query']);
				foreach ($words as $word)
				{
					$regexp = ereg_replace('[\.\*\?\+\(\)\{\}\^\$\|\\]','\\\\0' , $word);
					foreach ($fields as $field)
					{
						if (ereg($regexp, $article[$field])) $this->so->update_keywords($this->article_id, $word, $upgrade_key);
					}
				}
			}

			return $article;
		}

		function download_file_checks($art_id, $filename)
		{
			if (!$article = $this->get_article($art_id)) $this->die_peacefully('Error downloading file');
			if (!$this->check_permission($this->read_right)) $this->die_peacefully('You have not the proper permissions to do that');
			$found_file = False;
			foreach ($article['files'] as $article_file)
			{
				if ($article_file['file'] == $filename) $found_file = True;
			}
			if (!$found_file) $this->die_peacefully("Error: file doesn't exist in the database");
		}

		function get_comments($art_id, $limit = False)
		{
			if ($limit) $limit = $this->preferences['num_comments'];
			$comments = $this->so->get_comments($art_id, $limit);
			$this->num_comments = $this->so->num_comments;
			return $comments;
		}

		function get_related_articles($art_id)
		{
			$owners = $this->accessible_owners();
			return $this->so->get_related_articles($art_id, $owners);
		}

		function user_has_voted()
		{
			return $this->so->user_has_voted($this->article_id);
		}

		/**
		* @function add_rating
		*
		* @abstract	Registers user's vote
		* @author	Alejandro Pedraza
		* @param	$current_rating	int current number of votes in the level $rating (this saves me a trip to the db)
		* @returns	1 on success, 0 on failure
		**/
		function add_rating($current_rating)
		{
			if(!$this->so->add_vote($this->article_id, $_POST['Rate'], $current_rating)) return 0;
			if (!$this->so->add_rating_user($this->article_id)) return 0;
			return 1;
		}

		/**
		* @function add_comment
		*
		* @abstract	Stores article's comments
		* @author	Alejandro Pedraza
		* @returns	Success message or 0 if failure
		**/
		function add_comment()
		{
			$comment = $_POST['comment_box'];
			if ($this->admin_config['publish_comments'] == 'True')
			{
				$publish = True;
				$message = 'comm_ok';
			}
			else
			{
				$publish = False;
				$message = 'comm_submited';
			}
			if (!$this->so->add_comment($comment, $this->article_id, $publish)) return 0;
			return $message;
		}

		function add_link()
		{
			// first check permission
			if (!$this->check_permission($this->edit_right)) return 'no_perm';

			if(!$this->so->add_link($_POST['url'], $_POST['url_title'], $this->article_id)) return 'link_prob';

			$GLOBALS['phpgw']->historylog->add('AL', $this->article_id, $_POST['url'], '');
			return 'link_ok';
		}

		/**
		* @function	publish_article
		*
		* @abstract publishes article
		* @author	Alejandro Pedraza
		* @params	$art_id	Article ID. If not given uses current article
		* @params	$owner	Article owner. If not given uses owner of current article
		* @returns	Success or error message
		**/
		function publish_article($art_id=0, $owner=0)
		{
			if (!$art_id) $art_id = $this->article_id;

			// first check permission
			if (!$this->check_permission($this->publish_right, $owner)) return 'no_perm';

			if (!$this->so->publish_article($art_id)) return 'publish_err';
			return 'publish_ok';
		}

		function publish_comment()
		{
			$comment_id = (int)$_GET['pub_com'];
			// first check permission
			if (!$this->check_permission($this->edit_right)) return 'no_perm';

			if (!$this->so->publish_comment($this->article_id, $comment_id)) return 'publish_comm_err';
			return 'publish_comm_ok';
		}

		function delete_comment()
		{
			$comment_id = (int)$_GET['del_comm'];

			// check permission
			if (!$this->check_permission($this->edit_right)) return 'no_perm';

			if (!$this->so->delete_comment($this->article_id, $comment_id)) return 'del_comm_err';
			return 'del_comm_ok';
		}

		function delete_link()
		{
			// first check permission
			if (!$this->check_permission($this->edit_right)) return 'no_perm';

			if (!$this->so->delete_link($this->article_id, $_POST['delete_link'])) return 'link_del_err';

			$GLOBALS['phpgw']->historylog->add('RL', $this->article_id, $_POST['delete_link'], '');
			return 'link_del_ok';
		}

		/**
		* @function process_upload 
		*
		* @abstract	Uploads file to system
		* @author	Alejandro Pedraza
		* @return	string: error or confirmation message
		**/
		function process_upload()
		{
			// check permissions
			if (!$this->check_permission($this->edit_right)) return 'no_perm';
			// check something was indeed uploaded
			if ($_FILES['new_file']['error'] == 4) return 'nothing_uploaded';

			// TODO: check filename for invalid characters
		
			// check if basedir exists
			$test=$GLOBALS['phpgw']->vfs->get_real_info(array('string' => '/', 'relatives' => array(RELATIVE_NONE), 'relative' => False));
			if($test[mime_type]!='Directory')
			{
				return 'no_basedir';
			}

			// check if /kb  exists
			$test = @$GLOBALS['phpgw']->vfs->get_real_info(array('string' => '/kb', 'relatives' => array(RELATIVE_NONE), 'relative' => False));
			if($test[mime_type]!='Directory')
			{
				// if not, create it
				$GLOBALS['phpgw']->vfs->override_acl = 1;
				$GLOBALS['phpgw']->vfs->mkdir(array(
					'string' => '/kb',
					'relatives' => array(RELATIVE_NONE)
				));
				$GLOBALS['phpgw']->vfs->override_acl = 0;

				// test one more time
				$test = $GLOBALS['phpgw']->vfs->get_real_info(array('string' => '/kb', 'relatives' => array(RELATIVE_NONE), 'relative' => False));
				if($test[mime_type]!='Directory')
				{
					return 'no_kbdir';
				}
			}
			// prefix with article number
			$filename = 'kb' . $this->article_id . '-' . $_FILES['new_file']['name'];
			
			// check the file doesn't already exist (happens when double POSTing)
			$test = $GLOBALS['phpgw']->vfs->ls(array(
				'string'		=> '/kb/' . $filename,
				'relatives'		=> array(RELATIVE_NONE),
				'checksubdirs'	=> False,
				'nofiles'		=> False
			));
			if ($test[0]['name']) return 'overwrite';

			// at least, copy the file from /tmp to /kb
			$cd_args = array('string'	=> '/kb', 'relative' => False, 'relatives' => RELATIVE_NONE);
			if (!$GLOBALS['phpgw']->vfs->cd($cd_args)) return 'error_cd';

			$cp_args = array(
						'from'		=> $_FILES['new_file']['tmp_name'],
						'to'		=> $filename,
						'relatives'	=> array(RELATIVE_NONE|VFS_REAL, RELATIVE_ALL)
					);
			$GLOBALS['phpgw']->vfs->override_acl = 1; // should I implement ACL on this folder? Don't think so :>
			if (!$GLOBALS['phpgw']->vfs->mv($cp_args)) return 'error_cp';
			$GLOBALS['phpgw']->vfs->override_acl = 0;

			$this->so->add_file($this->article_id, $filename);

			$GLOBALS['phpgw']->historylog->add('AF', $this->article_id, $_FILES['new_file']['name'], '');
			return 'upload_ok';
		}

		function delete_file($current_files, $file = '')
		{
			if (!$file) $file = $_POST['delete_file'];

			// check permissions
			if (!$this->check_permission($this->edit_right)) return 'no_perm';

			// verify the file exists in the server
			$test = $GLOBALS['phpgw']->vfs->ls(array(
				'string'		=> '/kb/' . $file,
				'relatives'		=> array(RELATIVE_NONE),
				'checksubdirs'	=> False,
				'nofiles'		=> False
			));
			if ($test[0]['name'])
			{
				// the file is in the server, proceed to rm it
				$remove = $GLOBALS['phpgw']->vfs->rm(array(
					'string'	=> '/kb/' . $file,
					'relatives'	=> array(RELATIVE_NONE)
				));
				if (!$remove) return 'failure_delete';
				$in_server = True;

			}
			else
			{
				// the file was already missing
				$in_server = False;
			}

			// now delete it from the database
			ereg('^kb[0-9]*-(.*)', $file, $new_filename);
			if ($success = $this->so->delete_file($this->article_id, $file))
				$GLOBALS['phpgw']->historylog->add('RF', $this->article_id, $new_filename[1], '');
			if ($in_server && $success) return 'file_del_ok';
			if ($in_server && !$success) return 'file_db_del_err';
			if (!$in_server && $success) return 'file_noserv_db_ok';
			return 'file_noserv_db_err';
		}

		function add_related()
		{
			$parsed_list = array();
			$final_list = array();

			// validate list
			$list = explode(', ', $_POST['related_articles']);
			for ($i=0; $i<sizeof($list); $i++)
			{
				if ((int)$list[$i])
				{
					$parsed_list[] = (int)$list[$i];
				}
			}

			// check permissions on those articles
			$owners_list = $this->so->owners_list($parsed_list);
			foreach ($owners_list as $owner)
			{
				if ($this->check_permission($this->edit_right, $owner['user_id'])) $final_list[] = $owner['art_id'];
			}

			// update database
			if (!$this->so->add_related($this->article_id, $final_list)) return 'articles_not_added';

			$final_list = implode(', ', $final_list);
			$GLOBALS['phpgw']->historylog->add('AR', $this->article_id, $final_list, '');
			return 'articles_added';
		}

		function delete_related()
		{
			$this->so->delete_related($this->article_id, $_POST['delete_related']);
			$GLOBALS['phpgw']->historylog->add('DR', $this->article_id, $_POST['delete_related'], '');
		}

		function add_question()
		{
			$data = $_POST;
			$publish = ($this->admin_config['publish_questions'] == 'True')? True : False;
			return $this->so->add_question($data, $publish);
		}

		function get_question($q_id)
		{
			$question = $this->so->get_question($q_id);
			$username = $GLOBALS['phpgw']->accounts->get_account_name($question['user_id'], $lid, $fname, $lname);
			$question['username'] = $fname . ' ' . $lname;
			$question['creation'] = $GLOBALS['phpgw']->common->show_date($question['creation'], $GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
			return $question;
		}

		function mail_article($article_contents)
		{
			$GLOBALS['phpgw']->send = CreateObject('phpgwapi.send');
			$rc = $GLOBALS['phpgw']->send->msg('email', $_POST['recipient'], $_POST['subject'], $article_contents, '', '', '', $_POST['reply'], $_POST['reply'], 'html');
			if (!$rc)
			{
				 $message = 'Your message could <B>not</B> be sent!<BR>'."\n"
					. 'The mail server returned:<BR>'
					. "err_code: '".$GLOBALS['phpgw']->send->err['code']."';<BR>"
					. "err_msg: '".htmlspecialchars($GLOBALS['phpgw']->send->err['msg'])."';<BR>\n"
					. "err_desc: '".$GLOBALS['phpgw']->err['desc']."'.<P>\n";
			}
			if ($message) return $message;
			return 'mail_ok';
		}


		function die_peacefully($error_msg)
		{
			if (!$this->navbar_shown)
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();
			}
			echo "<div style='text-align:center; font-weight:bold'>" . lang($error_msg) . "</div>";
			$GLOBALS['phpgw']->common->phpgw_footer();
			die();
		}
	}

?>
