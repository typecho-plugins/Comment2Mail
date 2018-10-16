<?php

/**
 * typecho 评论邮件提醒 SMTP邮件服务
 * 兼容PHP 5.5及更高版本
 * @package Comment2Mail
 * @author Hoe
 * @version 1.0.0
 * @link http://www.hoehub.com
 */

//require './PHPMailer/src/Exception.php';
require dirname(__FILE__) . '/PHPMailer/src/PHPMailer.php';
require dirname(__FILE__) . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Comment2Mail_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return string
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = [__CLASS__, 'event'];
        Typecho_Plugin::factory('Widget_Comments_Edit')->finishComment = [__CLASS__, 'event'];
        return _t('请配置邮箱SMTP选项!');
    }

    public function event($comment)
    {
        // TODO 发件人和收件人不能错
        // 回复评论
        if (0 < $comment->parent) {
            $widget = new Widget_Abstract_Comments(new Typecho_Request(), new Typecho_Response(), NULL);
            $db = Typecho_Db::get();
            // 查询
            $select = $widget->select()->where('coid' . ' = ?', $comment->parent)->limit(1);
            $parent = $db->fetchRow($select, [$widget, 'push']); // 获取上级评论对象
            self::sendMail($comment, $parent->mail); // 收件人为上级评论者
        } else { // 普通评论
            self::sendMail($comment); // 收件人为博主
        }
    }
    public static function sendMail($comment, $recipient = null)
    {
        try {
            $mail = new PHPMailer(true);
            // 获取系统配置选项
            $options = Helper::options();
            // 获取插件配置
            $comment2Mail = $options->plugin('Comment2Mail');
            //Server settings
            $mail->isSMTP();
            $mail->Host = $comment2Mail->STMPHost; // Specify main and backup SMTP servers
            $mail->SMTPAuth = true; // Enable SMTP authentication
            $mail->Username = $comment2Mail->smtpUserName; // SMTP username
            $mail->Password = $comment2Mail->smtpPassword;//'ahvdpvicgomqbahe'; // SMTP password
            $mail->SMTPSecure = $comment2Mail->SMTPSecure; // Options: '', 'ssl' or 'tls'. Enable TLS encryption, `ssl` also accepted
            $mail->Port = $comment2Mail->smtpPort; // TCP port to connect to

            //Recipients
            $mail->setFrom($comment2Mail->from, $comment2Mail->fromName);
            if (empty($recipient)) $recipient = $comment->mail;
            $name = $comment->author;
            $mail->addAddress($recipient, $name); // Add a recipient

            $content = $comment->text; // 评论内容
            //Content
            $mail->isHTML(); // Set email format to HTML
            $mail->Subject = $name . '发来的邮件';
            $mail->Body    = $content;

//            $mail->send();
            // 记录日志
            if ($comment2Mail->log) {
                if ($mail->isError()) {
                    file_put_contents(dirname(__FILE__) . '/log.txt', $mail->ErrorInfo, FILE_APPEND);

                }
                file_put_contents(dirname(__FILE__) . '/log.txt', $comment2Mail, FILE_APPEND);
                file_put_contents(dirname(__FILE__) . '/log.txt', json_encode($comment), FILE_APPEND);
                file_put_contents(dirname(__FILE__) . '/log.txt', "\n ok ", FILE_APPEND);
            }

        } catch (Typecho_Plugin_Exception $e) {
            file_put_contents(dirname(__FILE__) . '/log.txt', $e, FILE_APPEND);
        }
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     */
    public static function deactivate()
    {
        $at = date('Y-m-d H:i:s');
        $str = $at . ' 禁用了 ' . __CLASS__ . "\n";
        file_put_contents(dirname(__FILE__) . '/log.txt', $str, FILE_APPEND);
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 记录log
        $log = new Typecho_Widget_Helper_Form_Element_Checkbox('log', ['log' => _t('记录日志')], 'log', _t('记录日志'), _t('启用后将当前目录生成一个log.txt 注:目录需有写入权限'));
        $form->addInput($log);

        $layout = new Typecho_Widget_Helper_Layout();
        $layout->html(_t('<h3>邮件服务配置:</h3>'));
        $form->addItem($layout);

        // smtp服务
        $STMPHost = new Typecho_Widget_Helper_Form_Element_Text('STMPHost', NULL, 'smtp.qq.com', _t('SMTP服务器地址'), _t('如:smtp.163.com,smtp.gmail.com,smtp.exmail.qq.com,smtp.sohu.com,smtp.sina.com'));
        $form->addInput($STMPHost);

        // SMTP用户名
        $smtpUserName = new Typecho_Widget_Helper_Form_Element_Text('smtpUserName', NULL, NULL, _t('SMTP登录用户'), _t('SMTP登录用户名，一般为邮箱地址'));
        $form->addInput($smtpUserName);

        // SMTP密码
        $smtpPassword = new Typecho_Widget_Helper_Form_Element_Text('smtpPassword', NULL, NULL, _t('SMTP登录密码'), _t('为QQ邮箱以例: <a href="https://service.mail.qq.com/cgi-bin/help?subtype=1&&no=1001256&&id=28" target="_blank">查看</a>'));
        $form->addInput($smtpPassword);

        // 服务器安全模式
        $SMTPSecure = new Typecho_Widget_Helper_Form_Element_Radio('SMTPSecure', array('' => _t('无安全加密'), 'ssl' => _t('SSL加密'), 'tls' => _t('TLS加密')), 'none', _t('SMTP加密模式'));
        $form->addInput($SMTPSecure);

        // SMTP server port
        $smtpPort = new Typecho_Widget_Helper_Form_Element_Text('smtpPort', NULL, '25', _t('SMTP服务端口'), _t('默认25 ssl为465'));
        $form->addInput($smtpPort);

        $layout = new Typecho_Widget_Helper_Layout();
        $layout->html(_t('<h3>邮件信息配置:</h3>'));
        $form->addItem($layout);

        // 发件邮箱
        $from = new Typecho_Widget_Helper_Form_Element_Text('from', NULL, NULL, _t('发件邮箱'), _t('用于发送邮件的邮箱'));
        $form->addInput($from);
        // 发件人姓名
        $fromName = new Typecho_Widget_Helper_Form_Element_Text('fromName', NULL, NULL, _t('发件人姓名'), _t('发件人姓名'));
        $form->addInput($fromName);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 插件实现方法
     *
     * @access public
     * @return void
     */
    public static function render()
    {
    }

}
