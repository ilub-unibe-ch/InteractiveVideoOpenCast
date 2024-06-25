<?php
require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/InteractiveVideo/VideoSources/interface.ilInteractiveVideoSourceGUI.php';
require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/InteractiveVideo/VideoSources/plugin/InteractiveVideoOpenCast/class.ilInteractiveVideoOpenCast.php';
require_once 'Customizing/global/plugins/Services/COPage/PageComponent/OpencastPageComponent/vendor/autoload.php';
require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/vendor/autoload.php';

use ILIAS\DI\Container;
use srag\Plugins\Opencast\Container\Init;
use ILIAS\Data\URI;

/**
 * Class ilInteractiveVideoOpenCastGUI
 */
class ilInteractiveVideoOpenCastGUI implements ilInteractiveVideoSourceGUI
{

    const PLUGIN_CLASS_NAME = ilOpencastPageComponentPlugin::class;
    const CMD_CANCEL = "cancel";
    const CMD_CREATE = "create";
    const CMD_EDIT = "edit";
    const CMD_SELECT = "select";
    const CMD_INSERT = "insert";
    const CMD_UPDATE = "update";
    const CMD_APPLY_FILTER = "applyFilter";
    const CMD_RESET_FILTER = "resetFilter";
    const CUSTOM_CMD = 'ocpc_cmd';
    const POST_SIZE = 'size';
    const CMD_SAVE = 'save';
    const CMD_INDEX = 'index';
    const OPC_DUMMY_ID = 'opc_dummy';
    public const PROP_EVENT_ID = 'event_id';

    /**
     * @var Container
     */
    protected $dic;

    /**
     * @var ilCtrl
     */
    protected $ilCtrlFake;

    /**
     * @var string
     */
    protected $command_url;

    /**
     * @var string
     */
    protected $ajax_url;

	/**
	 * @param ilRadioOption $option
	 * @param               $obj_id
	 * @return ilRadioOption
	 */
	public function getForm($option, $obj_id) : ilRadioOption
	{
		global $tpl, $DIC;
		$this->dic = $DIC;
        $ctrl = $DIC->ctrl();

        $ctrl->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_plugin_ctrl', ilInteractiveVideoOpenCastGUI::class);
        $ctrl->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_plugin_function', 'getAjaxOpenCastTable');
        $ctrl->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_source_id', 'opc');
        $ctrl->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_custom_js', 'il.opcMediaPortalAjaxQuery.openSelectionModal(false)');

        $object = new ilInteractiveVideoOpenCast();
        $object->doReadVideoSource($obj_id);
        $DIC->language()->toJSMap([
            'select_video' => ilInteractiveVideoPlugin::getInstance()->txt('opc_select_video'),
            'title' => ilInteractiveVideoPlugin::getInstance()->txt('opc_title'),
            'opc_insert' => ilInteractiveVideoPlugin::getInstance()->txt('opc_insert')
            ], $DIC->ui()->mainTemplate());
        $get = $DIC->http()->wrapper()->query();
        if($get->has('cmd') && $get->retrieve('cmd', $DIC->refinery()->kindlyTo()->string()) === 'create'){
            $info_test = new ilNonEditableValueGUI('', 'oc_info_text');
            $info_test->setValue(ilInteractiveVideoPlugin::getInstance()->txt('please_create_object_first'));
            $option->addSubItem($info_test);

            $opc_inject_text = new ilHiddenInputGUI('opc_id');
            $opc_inject_text->setValue(self::OPC_DUMMY_ID);
            $option->addSubItem($opc_inject_text);
        } else {
            $tpl->addJavaScript('Customizing/global/plugins/Services/Repository/RepositoryObject/InteractiveVideo/VideoSources/plugin/InteractiveVideoOpenCast/js/opcMediaPortalAjaxQuery.js');
            $opc_id = new ilHiddenInputGUI( 'opc_id');
            $option->addSubItem($opc_id);
            $info_test = new ilNonEditableValueGUI('', 'opc_id_text');
            $info_test->setValue('');
            $option->addSubItem($info_test);
            $opc_url = new ilHiddenInputGUI('opc_url');
            $option->addSubItem($opc_url);
        }

        $tpl_modal = new ilTemplate('Customizing/global/plugins/Services/Repository/RepositoryObject/InteractiveVideo/VideoSources/plugin/InteractiveVideoOpenCast/tpl/tpl.modal.html', false, false);

        $modal = ilModalGUI::getInstance();
        $modal->setId("OpencastSelectionModal");
        $modal->setType(ilModalGUI::TYPE_LARGE);
        $tpl_modal->setVariable('MODAL', $modal->getHTML());

        $this->ajax_url = $DIC->ctrl()->getLinkTargetByClass(['ilRepositoryGUI', 'ilObjInteractiveVideoGUI'], 'getAjaxOpenCastTable','', true, false);
        $tpl_modal->setVariable('OPENCAST_AJAX_URL', $this->ajax_url);

        $this->dic->ui()->mainTemplate()->setVariable('WEBDAV_MODAL', $tpl_modal->get());
        $action_text = ilInteractiveVideoPlugin::getInstance()->txt('opc_select_video');
        $opc_inject_text = new ilHiddenInputGUI('opc_inject_text');
        $opc_inject_text->setValue($action_text);
        $option->addSubItem($opc_inject_text);

        if($object->getOpcId() === self::OPC_DUMMY_ID){
            $this->dic->ui()->mainTemplate()->addOnLoadCode('il.opcMediaPortalAjaxQuery.openSelectionModal(true);');
        }

		return $option;
	}

    public function convertURIToOpencastId() {
        global $DIC;
        $get = $DIC->http()->wrapper()->query();
        if($get->has('xvid_id_url')) {
            $url = $get->retrieve('xvid_id_url', $DIC->refinery()->kindlyTo()->string());
            $video_url = $this->getVideoUrl($url);
            if($video_url !== null) {

            }
        }
    }

	public function getAjaxOpenCastTable(){
        global $DIC;
        $get = $DIC->http()->wrapper()->query();
        if($get->has('xvid_id_url')) {
            $url = $get->retrieve('xvid_id_url', $DIC->refinery()->kindlyTo()->string());
        }
        $tpl_json = ilInteractiveVideoPlugin::getInstance()->getTemplate('default/tpl.show_question.html', false, false);
        $tpl_json->setVariable('JSON', $this->getTable());
        $tpl_json->show("DEFAULT", false, true );
        exit();
    }

	/**
	 * @param ilPropertyFormGUI $form
	 * @return bool
	 */
	public function checkForm($form) :bool
	{
		$opc_url = ilUtil::stripSlashes($_POST['opc_url']);
		if($opc_url != '' )
		{
			return true;
		}
		return false;
	}
	

	/**
	 * @param ilTemplate $tpl
	 * @return ilTemplate
	 */
	public function addPlayerElements($tpl)
	{
		$tpl->addJavaScript('Customizing/global/plugins/Services/Repository/RepositoryObject/InteractiveVideo/VideoSources/plugin/InteractiveVideoOpenCast/js/jquery.InteractiveVideoOpenCastPlayer.js');
        ilPlayerUtil::initMediaElementJs($tpl, false);
		return $tpl;
	}

    /**
     * @param                       $player_id
     * @param ilObjInteractiveVideo $obj
     * @return ilTemplate
     */
    public function getPlayer($player_id, $obj)
	{
		$player		= new ilTemplate('Customizing/global/plugins/Services/Repository/RepositoryObject/InteractiveVideo/VideoSources/plugin/InteractiveVideoOpenCast/tpl/tpl.video.html', false, false);
		$instance	= new ilInteractiveVideoOpenCast();
		$instance->doReadVideoSource($obj->getId());
        if($instance->getOpcId() !== self::OPC_DUMMY_ID) {
            $player->setVariable('PLAYER_ID', $player_id);
            $url = xoctSecureLink::signPlayer($this->getVideoUrl($instance->getOpcId()));
            # $signed_url = xoctConf::getConfig(xoctConf::F_SIGN_DOWNLOAD_LINKS) ? xoctSecureLink::signDownload($url) : $url;
            $player->setVariable('OPC_URL', $url);
        }
		return $player;
	}

	/**
	 * @param array                 $a_values
	 * @param ilObjInteractiveVideo $obj
	 */
	public function getEditFormCustomValues(array &$a_values, $obj)
	{
		$instance = new ilInteractiveVideoOpenCast();
		$instance->doReadVideoSource($obj->getId());

        $a_values[ilInteractiveVideoOpenCast::FORM_ID_FIELD] = '';
        if($instance->getOpcId() !== self::OPC_DUMMY_ID){
            $a_values[ilInteractiveVideoOpenCast::FORM_ID_FIELD] = $instance->getOpcId();
        }

		$a_values[ilInteractiveVideoOpenCast::FORM_URL_FIELD] = $instance->getOpcUrl();
	}

	/**
	 * @param $form
	 */
	public function getConfigForm($form)
	{
        $event_id = $_GET[VideoSearchTableGUI::GET_PARAM_EVENT_ID];
	}

	/**
	 * @return boolean
	 */
	public function hasOwnConfigForm() : bool
	{
		return false;
	}

    protected function getTable() : string
    {
        global $DIC;
        $this->container = Init::init($DIC);
        $this->plugin = ilOpencastPageComponentPlugin::getInstance();
        $ui = $this->container->uiIntegration($this->plugin);
        $DIC->ctrl()->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_plugin_ctrl', ilInteractiveVideoOpenCastGUI::class);
        $DIC->ctrl()->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_plugin_function', 'getAjaxOpenCastTable');
        $DIC->ctrl()->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_source_id', 'opc');
        $target_url = new URI(ILIAS_HTTP_PATH . '/' . $DIC->ctrl()->getLinkTarget(new ilObjInteractiveVideoGUI(), 'convertURIToOpencastId'));

        $table = $DIC->ui()->renderer()->render([
                    $ui->mine()->asItemGroup(
                        $target_url,
                        'xvid_id_url'
                    )
                ]);



        return $table;
    }


    /**
     * @param string $event_id
     * @return string
     * @throws ilException
     * @throws xoctException
     */
    protected function getVideoUrl(string $event_id) : string {
        $event = xoctInternalAPI::getInstance()->events()->read($event_id);
        $download_dtos = $event->publications()->getDownloadDtos(); // sortiert nach Auflösung (descending)
        if (empty($download_dtos)) {
            throw new ilException('Video with id ' . $event_id . ' has no valid download url');
        }
        foreach ($download_dtos as $usage_type => $content) {
            foreach ($content as $usage_id => $download_dtos) {
                $download_pub_usage = null;
                if($download_dtos !== null) {
                    $first = $download_dtos{0}->getUrl();
                }
                if (is_null($download_pub_usage)) {
                    continue;
                }
            }
        }
        return $first;
    }
}