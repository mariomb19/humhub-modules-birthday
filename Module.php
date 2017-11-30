<?php

namespace humhub\modules\birthday;

use Yii;
use humhub\modules\birthday\widgets\BirthdaySidebarWidget;
use humhub\models\Setting;
use yii\helpers\Url;
use humhub\modules\user\models\User;
use yii\helpers\Console;

/**
 * BirthdayModule is responsible for the the birthday functions.
 *
 * @author Sebastian Stumpf
 */
class Module extends \humhub\components\Module
{

    /**
     * On build of the dashboard sidebar widget, add the birthday widget if module is enabled.
     *
     * @param type $event
     */
    public static function onSidebarInit($event)
    {
        if (Yii::$app->user->isGuest) {
            return;
        }

        $event->sender->addWidget(BirthdaySidebarWidget::className(), array(), array('sortOrder' => 200));
    }

    /**
     * @inheritdoc
     */
    public function getConfigUrl()
    {
        return Url::to(['/birthday/config']);
    }

    /**
     * @inheritdoc
     */
    public function enable()
    {
        parent::enable();
        Setting::Set('shownDays', 2, 'birthday');
    }

    /**
     * Send notification of near bdays.
     * Coming soon, you'll can specify how much days before you want it.
     *
     * @param type $event
     */
    public static function onCronRun($event) {

        $controller = $event->sender;
        if(date('l') == 'Friday'){
          $range = 3;
        }
        elseif(date('l') != 'Saturday' && date('l') != 'Sunday'){
          $range = 1;
        }

        $birthdayCondition = "DATE_ADD(profile.birthday,
                INTERVAL YEAR(CURDATE())-YEAR(profile.birthday)
                         + IF((CURDATE() > DATE_ADD(`profile`.birthday, INTERVAL (YEAR(CURDATE())-YEAR(profile.birthday)) YEAR)),1,0) YEAR)
            BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL " . $range . " DAY)";

        $users = User::find()
                ->joinWith('profile')
                ->where($birthdayCondition)
                ->active()
                ->limit(10)
                ->orderBy('birthday')
                ->all();
/*
        $tomorrow = new \DateTime('tomorrow');
        $bdayersTomorrow = array();
        $bdayersToday = array();
        $tomorrow = $tomorrow->format('d-m');
        foreach ($users as $user){
            $date = new \DateTime($user->profile->birthday);
            $date = $date->format('d-m');
            if ($date == $tomorrow){
                $bdayersTomorrow[] = $user;
            } else {
                $bdayersToday [] = $user;
            }
        }
*/
        $bdayers = array();
        foreach ($users as $user){
          $bdayers [] = $user;
        }
        $usersToMail = User::find()->where(['user.status' => User::STATUS_ENABLED])
                                    ->all();

        Console::startProgress($done, count($usersToMail), 'Birthday plugin is Sending update e-mails to users... ', false);
	    Yii::setAlias('@birthdaymail', __DIR__ . '\views\bdayMail' );


        // Bday mail notification
        if ( count($users) ) {
    	   foreach ($usersToMail as $userToMail){
                try {
                    $mail = Yii::$app->mailer->compose(['html' => '@birthdaymail'], ['tomorrowers' => $bdayersTomorrow, 'todayers' => $bdayersToday, 'bdayers' => $bdayers ]);
                    $mail->setFrom([Setting::Get('systemEmailAddress', 'mailing') => Setting::Get('systemEmailName', 'mailing')]);
                    $mail->setTo($userToMail->email);
                    $mail->setSubject(Yii::t('BirthdayModule.base', 'Birthdays'));
                    $mail->send();
                    $done = $done + 1;
                } catch (\Swift_SwiftException $ex) {
                       Console::output($ex->getMessage());
                } catch (Exception $ex) {

                    Yii::error('Could not send bday mail to: ' . $user->email . ' - Error:  ' . $ex->getMessage());
                }
    	    }
          $mail = Yii::$app->mailer->compose(['html' => '@birthdaymail'], ['usuarios'=>$usersToMail, 'tomorrowers' => $bdayersTomorrow, 'todayers' => $bdayersToday, 'bdayers' => $bdayers ]);
          $mail->setSubject('Email enviado de cumpleaños');
          $mail->setTo('mantenimiento@enclave.com.ar');
          $mail->send();
        } else {
          $controller->stdout('No bdays to send.' . PHP_EOL, \yii\helpers\Console::FG_GREEN);

          $mail = Yii::$app->mailer->compose();
          $mail->setSubject('Log email de cumpleaños');
          $mail->setTextBody('Se ejecuto el script de cumpleaños pero no hay usuarios asociados');
          $mail->setTo('mantenimiento@enclave.com.ar');
          $mail->send();

        }
        // End of Bday mailing
        Console::endProgress(true);

    }

}

?>
