<?php

namespace Sledgehammer\Core;

/**
 * Run the generate_static_library.php script from DevUtils.
 */
class GenerateStaticAutoLoader extends Util
{
    public function __construct()
    {
        parent::__construct('Optimize AutoLoader');
    }

    public function generateContent()
    {
        $warningMessage = 'This will generate a <b>AutoLoader.db.php</b> file which contains the file-locations for all detected classes and interfaces.<br /><br />';
        $warningMessage .= 'Changes in will no longer be detected by the AutoLoader!<br />';
        $warningMessage .= 'You\'ll need to rerun this script when definitions have changed.';
        $dialog = new Dialog('Optimize AutoLoader', $warningMessage, array('continue' => array('label' => 'Generate AutoLoader.db.php', 'class' => 'btn btn-danger')));
        $answer = $dialog->import($error);
        if ($answer == 'continue') {
            return new UtilScript('generate_static_autoloader.php', 'Generate AutoLoader database');
        }

        return $dialog;
    }
}
