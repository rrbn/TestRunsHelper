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

class ilTestRunsHelperUIHookGUI extends ilUIHookPluginGUI
{
    public function modifyGUI(
        $a_comp,
        $a_part,
        $a_par = []
    ) {
        if ($a_part === 'tabs') {
            // must be done here because ctrl and tabs are not initialized for all calls
            global $DIC;
            // ILIAS 7: ilTestParticipantsGUI statt ilTestParticipantsTableGUI
            if (strtolower($DIC->ctrl()->getCmdClass()) === strtolower(ilTestParticipantsGUI::class)) {
                require_once __DIR__ . '/class.ilTestRunsHelperGUI.php';
                $gui = new ilTestRunsHelperGUI();
                $gui->modifyToolbar();
            }
        }
    }
}
