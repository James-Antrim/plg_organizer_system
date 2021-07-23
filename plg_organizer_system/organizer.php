<?php
/**
 * @package     Organizer
 * @extension   plg_organizer_system
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

require_once JPATH_ROOT . '/components/com_organizer/autoloader.php';

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Uri\Uri;
use Organizer\Adapters\Database;
use Organizer\Helpers;
use Organizer\Helpers\Input;

defined('_JEXEC') or die;

/**
 * Organizer system plugin
 */
class PlgSystemOrganizer extends JPlugin
{
	private static $called = false;

	/**
	 * Migrates users' subscription links.
	 */
	public function onAfterInitialise()
	{
		if (Input::getCMD('option') === 'com_thm_organizer' and Input::getCMD('view') === 'schedule_export')
		{
			$auth     = Input::getString('auth');
			$format   = Input::getCMD('format');
			$groupID  = Input::getInt('poolIDs');
			$layout   = Input::getCMD('documentFormat');
			/** @noinspection PhpVariableNamingConventionInspection */
			$my       = Input::getBool('myschedule');
			$personID = Input::getInt('teacherIDs');
			$roomID   = Input::getInt('roomIDs');
			$url      = Uri::base() . '?option=com_organizer&view=';
			$userName = Input::getCMD('username');

			if ($groupID or $my or $personID or $roomID)
			{
				// The means to authenticate is missing, preemptively handled to make further processing uniform
				if (($my or $personID) and !($auth and $userName))
				{
					Helpers\OrganizerHelper::error(403);
				}

				$validMap = ['pdf' => ['a3', 'a4'], 'xls' => ['si'], 'ics' => []];

				if (!in_array($format, array_keys($validMap)) or ($layout and !in_array($layout, $validMap[$format])))
				{
					Helpers\OrganizerHelper::error(400);
				}

				$url .= "instances&format=$format";

				if ($format === 'pdf')
				{
					$layout = (empty($layout) or $layout === 'a4') ? 'GridA4' : 'GridA3';
				}
				elseif ($format === 'xls')
				{
					$layout = 'Instances';
				}

				$url .= $layout ? "&layout=$layout" : '';

				if ($groupID)
				{
					$url .=  "&groupID=$groupID";
				}

				if ($my)
				{
					$url .=  "&my=1";
				}

				if ($personID)
				{
					$url .=  "&personID=$personID";
				}

				if ($roomID)
				{
					$url .=  "&roomID=$roomID";
				}

				if ($userName)
				{
					$url .=  "&username=$userName";
				}

				if ($auth)
				{
					$url .=  "&auth=$auth";
				}
			}
			else
			{
				$url .= 'export';
			}

			Helpers\OrganizerHelper::getApplication()->redirect($url, 301);
		}
	}

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  bool
	 */
	public function onContentPrepareForm(Form $form, $data): bool
	{
		// Check we are manipulating a valid form.
		$name = $form->getName();

		if ($name !== 'com_menus.item' or !is_object($data) or empty($data->request))
		{
			return true;
		}

		if (empty($data->request['option']) or $data->request['option'] !== 'com_organizer')
		{
			return true;
		}

		if (empty($data->request['view']))
		{
			return false;
		}

		// Add the view configuration fields to the form.
		FormHelper::addFormPath(JPATH_ROOT . '/components/com_organizer/Layouts/HTML');
		$form->loadFile($data->request['view']);

		return true;
	}

	/**
	 * Method simulating the effect of a chron job by performing tasks on super user login.
	 *
	 * @return  bool  True on success.
	 */
	public function onUserAfterLogin(): bool
	{
		$user = Factory::getUser();
		if ($user->authorise('core.admin'))
		{
			return $this->purgeAttendance();
		}

		return true;
	}

	/**
	 * Ensures the users are logged in and redirected appropriately after being saved.
	 *
	 * @return void
	 */
	public function onUserAfterSave()
	{
		if (!$task = Helpers\Input::getTask() or $task !== 'register' or self::$called)
		{
			return;
		}

		$app         = Helpers\OrganizerHelper::getApplication();
		$form        = Helpers\Input::getFormItems();
		$credentials = ['username' => $form->get('username'), 'password' => $form->get('password1')];
		// Separate and parse the query to get the return URL
		$referrer = Helpers\Input::getInput()->server->get('HTTP_REFERER', '', 'raw');
		$query    = parse_url($referrer, PHP_URL_QUERY);
		parse_str($referrer, $query);
		$return = array_key_exists('return', $query) ? base64_decode($query['return']) : '';

		if ($app->login($credentials) and $return)
		{
			$app->redirect($return);
		}
	}

	/**
	 * Ensures that users with existing credentials use those during the account creation process.
	 *
	 * @param   array  $existing  the exising user entry
	 * @param   bool   $newFlag   a redundant flag
	 * @param   array  $user      the data entred by the user in the form
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onUserBeforeSave(array $existing, bool $newFlag, array $user): bool
	{
		if (!$task = Helpers\Input::getTask() or $task !== 'register' or self::$called)
		{
			return true;
		}

		if (!$filter = ComponentHelper::getParams('com_organizer')->get('emailFilter'))
		{
			return true;
		}

		if (strpos($user['email'], $filter) === false)
		{
			return true;
		}

		self::$called = true;

		$app         = Helpers\OrganizerHelper::getApplication();
		$form        = Helpers\Input::getFormItems();
		$credentials = ['username' => $form->get('username'), 'password' => $form->get('password1')];

		// Separate and parse the query to get the return URL
		$referrer = Helpers\Input::getInput()->server->get('HTTP_REFERER', '', 'raw');
		$query    = parse_url($referrer, PHP_URL_QUERY);
		parse_str($referrer, $query);
		$return = array_key_exists('return', $query) ? base64_decode($query['return']) : Uri::base();

		// An attempt was made to register using official credentials.
		if ($app->login($credentials))
		{
			$message = sprintf(Helpers\Languages::_('ORGANIZER_REGISTER_INTERNAL_SUCCESS'), $filter);
			Helpers\OrganizerHelper::message($message, 'success');
			$app->redirect($return);

			return true;
		}

		// Clear the standard error messages from the login routine.
		$app->getMessageQueue(true);
		$message = sprintf(Helpers\Languages::_('ORGANIZER_REGISTER_INTERNAL_FAIL'), $filter);
		Helpers\OrganizerHelper::message($message, 'warning');
		$app->redirect($return);

		return false;
	}

	/**
	 * Purges participant attendance older than four weeks.
	 *
	 * @return bool
	 */
	private function purgeAttendance(): bool
	{
		$then  = date('Y-m-d', strtotime("-29 days"));
		$query = "DELETE ip "
			. "FROM #__organizer_instance_participants AS ip "
			. "INNER JOIN #__organizer_instances AS i ON i.id = ip.instanceID "
			. "INNER JOIN #__organizer_blocks AS b ON b.id = i.blockID "
			. "WHERE b.date <= '$then'";
		Database::setQuery($query);

		return Database::execute();
	}
}
