<?php
/**
 * @category    Joomla plugin
 * @package     THM_Groups
 * @subpackage  plg_thm_organizer_content
 * @name        PlgContentThm_Organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2017 TH Mittelhessen
 * @license     GNU GPL v.2
 * @link        www.thm.de
 */

require_once JPATH_ROOT . '/components/com_organizer/autoloader.php';

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;

defined('_JEXEC') or die;

/**
 * THM Organizer content plugin
 *
 * @category  Joomla.Plugin.Content
 * @package   THM_Groups
 */
class PlgSystemOrganizer extends JPlugin
{
	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 */
	public function onContentPrepareForm(Form $form, $data)
	{
		// Check we are manipulating a valid form.
		$name = $form->getName();

		if ($name !== 'com_menus.item' or !is_object($data) or empty($data->request))
		{
			return true;
		}

		$option = empty($data->request['option']) ? '' : $data->request['option'];
		$view   = empty($data->request['view']) ? '' : $data->request['view'];
		if (empty($option) or $option !== 'com_thm_organizer' or empty($view))
		{
			return true;
		}

		// Add the view configuration fields to the form.
		FormHelper::addFormPath(JPATH_ROOT . '/components/com_organizer/Layouts/HTML');
		$form->loadFile($view);

		return true;
	}
}
