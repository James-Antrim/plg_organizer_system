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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Organizer\Adapters\Database;

defined('_JEXEC') or die;

/**
 * Organizer system plugin
 */
class PlgSystemOrganizer extends JPlugin
{
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
	 * @param   array  $user     Holds the user data.
	 * @param   array  $options  Array holding options (remember, autoregister, group).
	 *
	 * @return  bool  True on success.
	 */
	public function onUserAfterLogin($user, $options = [])
	{
		$user = Factory::getUser();
		if ($user->authorise('core.admin'))
		{
			$this->purgeAttendance();
		}

		return true;
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
