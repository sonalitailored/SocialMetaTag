<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.socialcustommetatag
 * @copyright   Copyright (C) 2016 - Tasolglobal. All rights reserved.
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since       3.6
 */

defined('_JEXEC') or die;
use Joomla\Registry\Registry;
JLoader::import('joomla.application.component.model');

/**
 * Iwcsocialmeta class for the Social Meta Plugin.
 *
 * @since  3.6
 */

class PlgContentIwcsocialmeta extends JPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  3.6
	 */
	protected $autoloadLanguage = true;

	/**
	 * Runs on content preparation
	 *
	 * @param   string  $context  The context for the data
	 * @param   object  $data     An object containing the data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   3.6
	 */
	public function onContentPrepareData($context, $data)
	{
		/*if (!in_array($context, array('com_content.article','com_contact.contact')))*/
		if (!in_array($context, array('com_content.article')))
		{
			return true;
		}

		$app = JFactory::getApplication();

		if ($app->isAdmin())
		{
			$str = (explode(".", $context));

			if (!empty($data))
			{
				$db    = JFactory::getDbo();
				$query = $db->getQuery(true);

				$query->select('m.*');
				$query->from($db->quoteName('#__iwcsocialmeta', 'm'));
				$query->where($db->quoteName('m.article_id') . ' = ' . (int) $data->id);
				$db->setQuery($query);

				$results = $db->loadObjectList();

				if ( !empty($results) )
				{
					$data->social_allowed    = explode(",", $results[0]->social_allowed);
					$data->social_meta_title = $results[0]->social_meta_title;
					$data->social_meta_desc  = $results[0]->social_meta_desc;
					$data->social_meta_type  = $results[0]->social_meta_type;
					$data->social_meta_image = $results[0]->social_meta_image;
				}
				else
				{
					$intro_image = $data->images['image_intro'];
					$full_image  = $data->images['image_fulltext'];

					if ($intro_image !== '' || $full_image !== '' )
					{
						if ($intro_image !== '' )
						{
							$data->social_meta_image = $intro_image;
						}
						else
						{
							$data->social_meta_image = $full_image;
						}
					}

					$data->social_meta_title = $data->title;
					$data->social_meta_desc  = $data->title;
					$data->social_meta_type  = $str[1];
				}
			}
		}

		return true;
	}

	/**
	 * Adds additional fields to the editing form
	 *
	 * @param   JForm  $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   3.6
	 */
	public function onContentPrepareForm($form, $data)
	{
		if (!($form instanceof JForm))
		{
			$this->_subject->setError('JERROR_NOT_A_FORM');

			return false;
		}

		// Check we are manipulating a valid form.
		$name = $form->getName();
		/*if (!in_array($name, array('com_content.article','com_contact.contact')))*/
		if (!in_array($name, array('com_content.article')))
		{
			return true;
		}

		$app = JFactory::getApplication();

		if ($app->isAdmin())
		{
			/* Add the fields to the form. */
			JForm::addFormPath(__DIR__ . '/forms');
			$form->loadFile('iwcsocialmeta', false);
		}

		return true;
	}

	/**
	 * Called right before the content is saved into the database
	 *
	 * @param   JForm    $context  The form to be altered.
	 * @param   mixed    $article  The associated data for the form.
	 * @param   boolean  $isNew    A boolean which is set to true if the content is about to be created.
	 *
	 * @return  boolean
	 *
	 * @since   3.6
	 */
	public function onContentBeforeSave($context,$article,$isNew)
	{
		$app     = JFactory::getApplication();
		$input   = $app->input;
		$session = JFactory::getSession();
		$session->set("formData", $input->post->get('jform', array(), 'array'));

		return true;
	}

	/**
	 * Adds additional fields to the editing form
	 *
	 * @param   JForm    $context  The form to be altered.
	 * @param   mixed    $article  The associated data for the form.
	 * @param   boolean  $isNew    A boolean which is set to true if the content is about to be created.
	 *
	 * @return  boolean
	 *
	 * @since   3.6
	 */
	public function onContentAfterSave($context, $article,$isNew)
	{
		$session = JFactory::getSession();
		$data    = $session->get("formData");
		$session->clear("formData");

		try
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__iwcsocialmeta'))
				->where($db->quoteName('article_id') . ' = ' . (int) $article->id);
			$db->setQuery($query);
			$db->execute();

			$query_in = 'INSERT INTO ' . $db->quoteName('#__iwcsocialmeta') . ' SET ' .
				$db->quoteName('article_id') . '        = ' . (int) $article->id . ',' .
				$db->quoteName('social_allowed') . '    = ' . $db->quote(implode(",", $data['social_allowed'])) . ',' .
				$db->quoteName('social_meta_title') . ' = ' . $db->quote($data['social_meta_title']) . ',' .
				$db->quoteName('social_meta_desc') . '  = ' . $db->quote($data['social_meta_desc']) . ',' .
				$db->quoteName('social_meta_type') . '  = ' . $db->quote($data['social_meta_type']) . ',' .
				$db->quoteName('social_meta_image') . ' = ' . $db->quote($data['social_meta_image']);

			$db->setQuery($query_in);
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			$msg = $e->getMessage();

			return false;
		}
		return true;
	}

	/**
	 * Called when article is being prepared for display,Here HTML can be injected into what will be displayed
	 *
	 * @param   string  $context   The context of the content being passed to the plugin.
	 * @param   object  &$article  The article object.Note $article->text is also available.
	 *
	 * @return  mixed   true if there is an error. Void otherwise.
	 *
	 * @since   1.6
	 */
	public function onContentPrepare($context, &$article)
	{
	}
}
