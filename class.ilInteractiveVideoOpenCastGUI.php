<?php

use ILIAS\DI\Container;
use srag\Plugins\Opencast\Container\Init;
use ILIAS\Data\URI;
use srag\Plugins\Opencast\Model\User\xoctUser;
use srag\Plugins\Opencast\Model\Event\EventAPIRepository;
use srag\Plugins\Opencast\Model\Event\Event;
use ILIAS\UI\Implementation\Component\Item\Group;

class ilInteractiveVideoOpenCastGUI implements ilInteractiveVideoSourceGUI
{
    public const CMD_CANCEL = "cancel";
    public const CMD_CREATE = "create";
    public const CMD_EDIT = "edit";

    public const CMD_UPDATE = "update";
    public const CMD_APPLY_FILTER = "applyFilter";
    public const CMD_RESET_FILTER = "resetFilter";
    public const CMD_SAVE = 'save';
    public const CMD_INDEX = 'index';
    public const OPC_DUMMY_ID = 'opc_dummy';
    public const XVID_ID_URL = 'xvid_id_url';
    public const XVID_OPC_URL = 'opc_url';

    protected ?Container $dic = null;

    protected array $series_name_cache = [];
    protected string $ajax_url;

    /**
     * @param $option
     * @param $obj_id
     * @return ilRadioOption
     * @throws JsonException
     * @throws ilCtrlException
     * @throws ilTemplateException
     */
    public function getForm($option, $obj_id): ilRadioOption
    {
        global $tpl;
        $this->dic = $this->getDIC();
        $ctrl = $this->dic->ctrl();

        $ctrl->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_plugin_ctrl', ilInteractiveVideoOpenCastGUI::class);
        $ctrl->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_plugin_function', 'getAjaxOpenCastTable');
        $ctrl->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_source_id', 'opc');
        $ctrl->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_custom_js', 'il.opcMediaPortalAjaxQuery.openSelectionModal(false)');

        $object = new ilInteractiveVideoOpenCast();
        $object->doReadVideoSource($obj_id);
        $this->dic->language()->toJSMap([
            'select_video' => ilInteractiveVideoPlugin::getInstance()->txt('opc_select_video'),
            'title' => ilInteractiveVideoPlugin::getInstance()->txt('opc_title'),
            'opc_insert' => ilInteractiveVideoPlugin::getInstance()->txt('opc_insert')
            ], $this->dic->ui()->mainTemplate());

        $get = $this->dic->http()->wrapper()->query();
        if($get->has('cmd') && $get->retrieve('cmd', $this->dic->refinery()->kindlyTo()->string()) === 'create') {
            $info_test = new ilNonEditableValueGUI('', 'oc_info_text');
            $info_test->setValue(ilInteractiveVideoPlugin::getInstance()->txt('please_create_object_first'));
            $option->addSubItem($info_test);

            $opc_inject_text = new ilHiddenInputGUI('opc_id');
            $opc_inject_text->setValue(self::OPC_DUMMY_ID);
            $option->addSubItem($opc_inject_text);
        } else {
            $tpl->addJavaScript('Customizing/global/plugins/Services/Repository/RepositoryObject/InteractiveVideo/VideoSources/plugin/InteractiveVideoOpenCast/js/opcMediaPortalAjaxQuery.js');
            $opc_id = new ilHiddenInputGUI('opc_id');
            $option->addSubItem($opc_id);
            $info_test = new ilNonEditableValueGUI('', 'opc_id_text');
            $info_test->setValue('');
            $option->addSubItem($info_test);
            $opc_url = new ilHiddenInputGUI(self::XVID_OPC_URL);
            $option->addSubItem($opc_url);
        }

        $tpl_modal = new ilTemplate('Customizing/global/plugins/Services/Repository/RepositoryObject/InteractiveVideo/VideoSources/plugin/InteractiveVideoOpenCast/tpl/tpl.modal.html', false, false);

        $modal = ilModalGUI::getInstance();
        $modal->setId("OpencastSelectionModal");
        $modal->setType(ilModalGUI::TYPE_LARGE);
        $tpl_modal->setVariable('MODAL', $modal->getHTML());

        $this->ajax_url = $this->dic->ctrl()->getLinkTargetByClass(['ilRepositoryGUI', 'ilObjInteractiveVideoGUI'], 'getAjaxOpenCastTable', '', true, false);
        $tpl_modal->setVariable('OPENCAST_AJAX_URL', $this->ajax_url);

        $this->dic->ui()->mainTemplate()->setVariable('WEBDAV_MODAL', $tpl_modal->get());
        $action_text = ilInteractiveVideoPlugin::getInstance()->txt('opc_select_video');
        $opc_inject_text = new ilHiddenInputGUI('opc_inject_text');
        $opc_inject_text->setValue($action_text);
        $option->addSubItem($opc_inject_text);

        if($object->getOpcId() === self::OPC_DUMMY_ID) {
            $this->dic->ui()->mainTemplate()->addOnLoadCode('il.opcMediaPortalAjaxQuery.openSelectionModal(true);');
        }

        return $option;
    }

    public function getAjaxOpenCastTable()
    {
        $dic = $this->getDIC();
        $get = $dic->http()->wrapper()->query();
        if($get->has(self::XVID_ID_URL)) {
            $url = $get->retrieve(self::XVID_ID_URL, $dic->refinery()->kindlyTo()->string());
        }
        $tpl_json = ilInteractiveVideoPlugin::getInstance()->getTemplate('default/tpl.show_question.html', false, false);
        $tpl_json->setVariable('JSON', $this->getTable());
        $tpl_json->show("DEFAULT");
        exit();
    }

    /**
     * @param ilPropertyFormGUI $form
     * @return bool
     */
    public function checkForm($form): bool
    {
        $dic = $this->getDIC();
        $post = $dic->http()->wrapper()->post();
        if($post->has(self::XVID_OPC_URL)) {
            $opc_url = $post->retrieve(self::XVID_OPC_URL, $dic->refinery()->kindlyTo()->string());
            $opc_url = ilUtil::stripSlashes($opc_url);
            if($opc_url != '') {
                return true;
            }
        }
        return false;
    }


    /**
     * @param ilGlobalTemplate $tpl
     * @return ilGlobalTemplate
     */
    public function addPlayerElements($tpl)
    {
        $tpl->addJavaScript('Customizing/global/plugins/Services/Repository/RepositoryObject/InteractiveVideo/VideoSources/plugin/InteractiveVideoOpenCast/js/jquery.InteractiveVideoOpenCastPlayer.js');
        ilPlayerUtil::initMediaElementJs($tpl, false);
        return $tpl;
    }

    /**
     * @param $player_id
     * @param $obj
     * @return ilTemplate
     * @throws ilException
     * @throws xoctException
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
        if($instance->getOpcId() !== self::OPC_DUMMY_ID) {
            $a_values[ilInteractiveVideoOpenCast::FORM_ID_FIELD] = $instance->getOpcId();
        }

        $a_values[ilInteractiveVideoOpenCast::FORM_URL_FIELD] = $instance->getOpcUrl();
    }

    /**
     * @param $form
     */
    public function getConfigForm($form)
    {
        $dic = $this->getDIC();
        $get = $dic->http()->wrapper()->query();
        if($get->has(VideoSearchTableGUI::GET_PARAM_EVENT_ID)) {
            $event_id = $get->retrieve(VideoSearchTableGUI::GET_PARAM_EVENT_ID, $dic->refinery()->kindlyTo()->string());
            $event_id = ilUtil::stripSlashes($event_id);
        }
    }

    /**
     * @return boolean
     */
    public function hasOwnConfigForm(): bool
    {
        return false;
    }

    protected function getTable(): string
    {
        $dic = $this->getDIC();
        $this->container = Init::init($dic);
        $this->plugin = ilOpencastPageComponentPlugin::getInstance();
        //$ui = $this->container->uiIntegration($this->plugin);
        $dic->ctrl()->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_plugin_ctrl', ilInteractiveVideoOpenCastGUI::class);
        $dic->ctrl()->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_plugin_function', 'getAjaxOpenCastTable');
        $dic->ctrl()->setParameter(new ilObjInteractiveVideoGUI(), 'xvid_source_id', 'opc');
        $target_url = new URI(ILIAS_HTTP_PATH . '/' . $dic->ctrl()->getLinkTarget(new ilObjInteractiveVideoGUI(), '#'));

        return $dic->ui()->renderer()->render([$this->asItemGroup($target_url,self::XVID_ID_URL)]);
    }

    private function getEvents(
        int $offset = 0,
        int $limit = 1000,
        string $sort = 'title',
        string $order = 'ASC'
    ): array {
        // the api doesn't deliver a max count, so we fetch (limit + 1) to see if there should be a 'next' page
        try {
            $xoct_user = xoctUser::getInstance($this->container->ilias()->user());
            $identifier = $xoct_user->getIdentifier();
            if ($identifier === '') {
                return [];
            }

            $filter = $this->filter_data ?? [];
            $filter = array_filter($filter, static fn($value): bool => $value !== '');
            $filter['status'] = 'EVENTS.EVENTS.STATUS.PROCESSED';


            $events = (array) Init::init($this->dic)->get(EventAPIRepository::class)->getFiltered(
                $filter,
                '',
                [$xoct_user->getUserRoleName()],
                $offset,
                $limit,
                "$sort:$order",
                true
            );

        } catch (\Throwable $e) {
            throw $e;
        }

        return array_filter($events, static function (Event $event): bool {
            return $event->getProcessingState() === Event::STATE_SUCCEEDED;
        });
    }

    public function asItemGroup(
        URI $target_url,
        string $parameter_name = 'event_id'
    ): Group {
        $items = [];

        $t = fn(string $key): string => $key;

        /** @var Event $event */
        foreach ($this->getEvents() as $event) {
            $action = (string) $target_url->withParameter(
                $parameter_name, $event->getIdentifier()
            );
            $items[] = $this->dic->ui()->factory()
                ->item()
                ->standard(
                    $this->dic->ui()->factory()->link()->standard(
                        $event->getTitle(),
                        $action
                    )
                )->withActions(
                    $this->dic->ui()->factory()->dropdown()->standard([
                        $this->dic->ui()->factory()->link()->standard(
                            $t("select"),
                            $action
                        ),
                    ])
                )
                ->withProperties([
                    $t("event_date") => $event->getStart()->format('d.m.Y H:i'),
                    $t("event_series") => $this->getSeriesName($event),
                    $t("event_presenter") => implode(", ", $event->getPresenter()),
                ])->withLeadImage(
                    $this->dic->ui()->factory()->image()->responsive(
                        $event->publications()->getThumbnailUrl(),
                        'src'
                    )->withAction($action)
                );
        }

        return $this->dic->ui()->factory()->item()->group(
            "Events",
            $items
        );
    }

    protected function getSeriesName(Event $event): string
    {
        $series_id = $event->getSeries();
        if (isset($this->series_name_cache[$series_id])) {
            return $this->series_name_cache[$series_id];
        }

        $series_name = "Test";//Init::init($this->dic)->get(EventAPIRepository::class)->find($series_id)->getMetadata()->getField('title')->getValue();

        return $this->series_name_cache[$series_id] = $series_name;
    }

    /**
     * @param string $event_id
     * @return string
     * @throws ilException
     * @throws xoctException
     */
    protected function getVideoUrl(string $event_id): string
    {

        $event = xoctInternalAPI::getInstance()->events()->read($event_id);
        $download_dtos = $event->publications()->getDownloadDtos(); // sortiert nach Auflösung (descending)
        if (empty($download_dtos)) {
            throw new ilException('Video with id ' . $event_id . ' has no valid download url');
        }
        foreach ($download_dtos as $usage_type => $content) {
            foreach ($content as $usage_id => $download_dtos) {
                if($download_dtos !== null) {
                    $first = $download_dtos[0]->getUrl();
                    return $first;
                }
            }
        }
        return '';
    }

    private function getDIC()
    {
        if($this->dic === null) {
            global $DIC;
            $this->dic = $DIC;
        }
        return $this->dic;
    }
}
