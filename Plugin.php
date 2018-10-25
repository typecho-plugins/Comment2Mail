<?php

/**
 * typecho 评论邮件提醒 SMTP邮件服务
 * 兼容PHP 5.5及更高版本
 * @package Comment2Mail
 * @author Hoe
 * @version 1.0.0
 * @link http://www.hoehub.com
 */

require dirname(__FILE__) . '/PHPMailer/src/PHPMailer.php';
require dirname(__FILE__) . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

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

    /**
     * @param Widget_Comments_Edit|Widget_Feedback $comment
     * @throws Exception
     * @throws Typecho_Db_Exception
     * 评论/回复时的回调事件
     */
    public static function event($comment)
    {
        $recipients = [];
        if (0 < $comment->parent) { // 发信给上级评论人&博主
            $db = Typecho_Db::get();
            $widget = new Widget_Abstract_Comments(new Typecho_Request(), new Typecho_Response(), NULL);
            // 查询
            $select = $widget->select()->where('coid' . ' = ?', $comment->parent)->limit(1);
            $parent = $db->fetchRow($select, [$widget, 'push']); // 获取上级评论对象
            if ($parent) {
                $parentAuthor = [
                    'name' => $parent['author'],
                    'mail' => $parent['mail'],
                ];
                $recipients[] = $parentAuthor;
            }
        }
        self::sendMail($comment, $recipients);
    }

    /**
     * @param Widget_Comments_Edit|Widget_Feedback $comment
     * @param array $recipients
     * @throws Exception
     * 发信方法
     */
    public static function sendMail($comment, $recipients)
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

            // 给博主发信
            $recipients[] = [
                'name' => $comment2Mail->fromName,
                'mail' => $comment2Mail->from,
            ];
            foreach ($recipients as $value) {
                $mail->addAddress($value['mail'], $value['name']); // Add a recipient
            }
            $mail->Subject = '来自[' . $options->title . ']站点 的新消息';

            $mail->isHTML(); // Set email format to HTML
            //Content
            $content  = '评论人: ' . $comment->author; // 评论人
            $content .= '评论内容: ' . $comment->text; // 评论内容
            $mail->Body = $content;
            $mail->send();

            // 记录日志
            if ($comment2Mail->log && $mail->isError()) {
                $fileName = dirname(__FILE__) . '/log.txt';
                $data = $mail->ErrorInfo;
                file_put_contents($fileName, $data, FILE_APPEND);
            }

        } catch (Typecho_Plugin_Exception $e) {
            $fileName = dirname(__FILE__) . '/log.txt';
            file_put_contents($fileName, $e, FILE_APPEND);
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
