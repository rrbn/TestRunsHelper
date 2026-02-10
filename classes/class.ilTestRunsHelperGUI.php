<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\Plugin\TestRunsHelper\Helper;
use ILIAS\Plugin\TestRunsHelper\SelectForm;
use ILIAS\UI\Implementation\Component\SignalGeneratorInterface;
use ILIAS\Plugin\TestRunsHelper\PluginRenderer;

require_once __DIR__ . '/../src/Helper.php';
require_once __DIR__ . '/../src/SelectForm.php';
require_once __DIR__ . '/../src/PluginRenderer.php';

/**
 * @ilCtrl_IsCalledBy ilTestRunsHelperGUI: ilUIPluginRouterGUI
 */
class ilTestRunsHelperGUI
{
    private ilCtrl $ctrl;
    private ilAccessHandler $access;
    private ilGlobalTemplateInterface $tpl;
    private ilLanguage $lng;
    private ilToolbarGUI $toolbar;
    private Factory $ui_factory;
    private Renderer $ui_renderer;
    private ilPlugin $plugin;
    private ilErrorHandling $error;
    private SignalGeneratorInterface $signal_generator;

    private int $ref_id;
    private ilObjTest $test;
    private Helper $helper;
    private PluginRenderer $plugin_renderer;

    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->access = $DIC->access();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->toolbar = $DIC->toolbar();
        $this->lng = $DIC->language();
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();
        $this->error = $DIC['ilErr'];
        $this->signal_generator = $DIC["ui.signal_generator"];

        // ILIAS 7: Plugin-Zugriff ueber ilPluginAdmin statt component.factory
        $this->plugin = ilPluginAdmin::getPluginObjectById('teruhe');

        // ILIAS 7: AbstractComponentRenderer hat 6 Konstruktor-Parameter
        // (mit ImagePathResolver, aber ohne DataFactory)
        $this->plugin_renderer = new PluginRenderer(
            $DIC->ui()->factory(),
            $DIC["ui.template_factory"],
            $DIC->language(),
            $DIC["ui.javascript_binding"],
            $DIC->refinery(),
            $DIC["ui.pathresolver"]
        );

        // ILIAS 7: kein HTTP wrapper(), daher $_GET verwenden
        $this->ref_id = (int) ($_GET['ref_id'] ?? 0);
        $this->ctrl->saveParameter($this, 'ref_id');

        $this->test = new ilObjTest($this->ref_id);
        $this->helper = new Helper($this->test, $DIC->database());
    }

    public function executeCommand()
    {
        if (!$this->access->checkAccess('write', '', $this->ref_id)) {
            $this->error->raiseError($this->lng->txt('permission_denied'), $this->error->MESSAGE);
        }

        switch ($cmd = $this->ctrl->getCmd()) {
            case 'continuePasses':
                $this->$cmd();
                break;

            default:
                $this->error->raiseError(
                    $this->lng->txt('msg_unknown_value') . ' ' . $cmd,
                    $this->error->MESSAGE
                );
        }
    }

    public function modifyToolbar()
    {
        if ($this->access->checkAccess('write', '', $this->ref_id)
            && $this->helper->canPassesBeContinued()
        ) {
            $form = new SelectForm(
                $this->signal_generator,
                $this->helper->getFinishedParticipants(),
                'active_id',
                $this->ctrl->getLinkTargetByClass(['ilUIPluginRouterGUI', 'ilTestRunsHelperGUI'], 'continuePasses')
            );
            $rendered_form = $this->ui_factory->legacy($this->plugin_renderer->render($form, $this->ui_renderer));

            $modal = $this->ui_factory->modal()->roundtrip($this->plugin->txt('reopen_passes'), $rendered_form)
                ->withActionButtons([
                    $this->ui_factory->button()->primary($this->plugin->txt('reopen_passes'), '#')
                        ->withOnClick($form->getSubmitSignal())
                ]);

            $button = $this->ui_factory->button()->standard($this->plugin->txt('reopen_passes'), '#')
                ->withOnClick($modal->getShowSignal());

            if (!$this->helper->hasFinishedPasses()) {
                $button = $button->withUnavailableAction();
            }

            $this->toolbar->addComponent($button);
            $this->toolbar->addComponent($modal);
        }
    }

    private function continuePasses()
    {
        // ILIAS 7: kein HTTP wrapper(), daher $_POST verwenden
        $posted_ids = isset($_POST['active_id']) && is_array($_POST['active_id'])
            ? array_map('intval', $_POST['active_id'])
            : [];

        $active_ids = array_intersect(
            $posted_ids,
            array_keys($this->helper->getFinishedParticipants())
        );

        if (empty($active_ids)) {
            // ILIAS 7: String-Konstanten statt MESSAGE_TYPE_* Klassenkonstanten
            $this->tpl->setOnScreenMessage(
                'failure',
                $this->plugin->txt('please_select_participant'),
                true
            );
        } else {
            $affected = $this->helper->continuePasses($active_ids);

            if ($affected == 0) {
                $this->tpl->setOnScreenMessage(
                    'failure',
                    sprintf($this->plugin->txt('passes_reopened'), 0),
                    true
                );
            } elseif ($affected == 1) {
                $this->tpl->setOnScreenMessage(
                    'success',
                    $this->plugin->txt('pass_reopened') . ' '
                    . $this->plugin->txt('time_extension_note'),
                    true
                );
            } else {
                $this->tpl->setOnScreenMessage(
                    'success',
                    sprintf($this->plugin->txt('passes_reopened'), $affected) . ' '
                    . $this->plugin->txt('time_extension_note'),
                    true
                );
            }
        }

        // ILIAS 7: direkter URL-Redirect zurueck zum Test
        ilUtil::redirect('ilias.php?baseClass=ilRepositoryGUI&ref_id=' . $this->ref_id . '&cmdClass=ilobjtestgui&cmd=infoScreen');
    }
}
