<?php

/**
 * typecho 评论通过时发送邮件提醒
 * @package Comment2Mail
 * @author Hoe, h3110w0r1d
 * @version 1.3.1
 * @link https://github.com/typecho-plugins/Comment2Mail
 * version 1.0.1 博主回复别人时,不需要给博主发信 by Hoe
 * version 1.1.0 修改了邮件样式,邮件样式是utf8,避免邮件乱码 by Hoe
 * version 1.1.1 邮件里显示评论人邮箱 by Hoe
 * version 1.2.0 如果所有评论必须经过审核, 通知博主审核评论 by Hoe
 * version 1.2.1 如果是自己回复自己评论的, 不接收邮件 by Hoe
 * version 1.3.0 新增测试功能 by Hoe
 * version 1.3.1 添加收件邮箱选项，解决发信邮箱和博主邮箱不同时的问题 by h3110w0r1d
 * version 1.3.2 修复新版本 Typecho 报错“must be of type Typecho\Widget\Request, Typecho\Request given”的问题，根据 https://github.com/typecho/typecho/issues/1192 修复 by Hsiao-Feng
 */

require dirname(__FILE__) . '/PHPMailer/src/PHPMailer.php';
require dirname(__FILE__) . '/PHPMailer/src/SMTP.php';
require dirname(__FILE__) . '/PHPMailer/src/Exception.php';

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
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = [__CLASS__, 'finishComment']; // 前台提交评论完成接口
        Typecho_Plugin::factory('Widget_Comments_Edit')->finishComment = [__CLASS__, 'finishComment']; // 后台操作评论完成接口
        Typecho_Plugin::factory('Widget_Comments_Edit')->mark = [__CLASS__, 'mark']; // 后台标记评论状态完成接口
        Helper::addRoute('comment2mail_test', '/comment2mail/test', 'Comment2Mail_Action', 'action');
        return _t('请前往设置页面，配置邮箱SMTP选项!');
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
        Helper::removeRoute('comment2mail_test');
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
        $log = new Typecho_Widget_Helper_Form_Element_Checkbox('log', ['log' => _t('记录日志')], '', _t('记录日志'), _t('启用后将当前目录生成一个log.txt 注:目录需有写入权限'));
        $form->addInput($log);

        $layout = new Typecho_Widget_Helper_Layout();
        $layout->html(_t('<h3>邮件服务配置:</h3>'));
        $form->addItem($layout);

        // SMTP服务地址
        $STMPHost = new Typecho_Widget_Helper_Form_Element_Text('STMPHost', NULL, 'smtp.qq.com', _t('SMTP服务器地址'), _t('如:smtp.163.com,smtp.gmail.com,smtp.exmail.qq.com,smtp.sohu.com,smtp.sina.com'));
        $form->addInput($STMPHost->addRule('required', _t('SMTP服务器地址必填!')));

        // SMTP用户名
        $SMTPUserName = new Typecho_Widget_Helper_Form_Element_Text('SMTPUserName', NULL, NULL, _t('SMTP登录用户'), _t('SMTP登录用户名，一般为邮箱地址'));
        $form->addInput($SMTPUserName->addRule('required', _t('SMTP登录用户必填!')));

        // SMTP密码
        $description = _t('一般为邮箱登录密码, 有特殊如: QQ邮箱有独立的SMTP密码. 可参考: ');
        $description .= '<a href="https://service.mail.qq.com/cgi-bin/help?subtype=1&&no=1001256&&id=28" target="_blank">QQ邮箱</a> ';
        $description .= '<a href="https://mailhelp.aliyun.com/freemail/detail.vm?knoId=6521875" target="_blank">阿里邮箱</a> ';
        $description .= '<a href="https://support.office.com/zh-cn/article/outlook-com-%E7%9A%84-pop%E3%80%81imap-%E5%92%8C-smtp-%E8%AE%BE%E7%BD%AE-d088b986-291d-42b8-9564-9c414e2aa040?ui=zh-CN&rs=zh-CN&ad=CN" target="_blank">Outlook邮箱</a> ';
        $description .= '<a href="http://help.sina.com.cn/comquestiondetail/view/160/" target="_blank">新浪邮箱</a> ';
        $SMTPPassword = new Typecho_Widget_Helper_Form_Element_Text('SMTPPassword', NULL, NULL, _t('SMTP登录密码'), $description);
        $form->addInput($SMTPPassword->addRule('required', _t('SMTP登录密码必填!')));

        // 服务器安全模式
        $SMTPSecure = new Typecho_Widget_Helper_Form_Element_Radio('SMTPSecure', array('' => _t('无安全加密'), 'ssl' => _t('SSL加密'), 'tls' => _t('TLS加密')), 'none', _t('SMTP加密模式'));
        $form->addInput($SMTPSecure);

        // SMTP server port
        $SMTPPort = new Typecho_Widget_Helper_Form_Element_Text('SMTPPort', NULL, '25', _t('SMTP服务端口'), _t('默认25 SSL为465 TLS为587'));
        $form->addInput($SMTPPort);

        $layout = new Typecho_Widget_Helper_Layout();
        $layout->html(_t('<h3>邮件信息配置:</h3>'));
        $form->addItem($layout);

        // 发件邮箱
        $from = new Typecho_Widget_Helper_Form_Element_Text('from', NULL, NULL, _t('发件邮箱'), _t('用于发送邮件的邮箱'));
        $form->addInput($from->addRule('required', _t('发件邮箱必填!')));
        // 发件人姓名
        $fromName = new Typecho_Widget_Helper_Form_Element_Text('fromName', NULL, NULL, _t('发件人姓名'), _t('发件人姓名'));
        $form->addInput($fromName->addRule('required', _t('发件人姓名必填!')));
        // 博主(收件)邮箱
        $autherMail = new Typecho_Widget_Helper_Form_Element_Text('autherMail', NULL, NULL, _t('博主(收件)邮箱'), _t('用于接收评论通知的邮箱'));
        $form->addInput($autherMail->addRule('required', _t('博主(收件)邮箱必填!')));
        // 测试按钮
        $url = Helper::security()->getIndex('/comment2mail/test');
        $testBtnHtml = <<<HTML
<script>
    function testMailConf() {
        $(".response").html("请稍等……");
        const STMPHost     = $("input[name='STMPHost']").val();
        const SMTPUserName = $("input[name='SMTPUserName']").val();
        const SMTPPassword = $("input[name='SMTPPassword']").val();
        const SMTPSecure   = $("input[name='SMTPSecure']:checked").val();
        const SMTPPort     = $("input[name='SMTPPort']").val();
        const fromMail     = $("input[name='from']").val();
        const autherMail   = $("input[name='autherMail']").val();
        const data = {
            STMPHost: STMPHost,
            SMTPUserName: SMTPUserName,
            SMTPPassword: SMTPPassword,
            SMTPSecure: SMTPSecure,
            SMTPPort: SMTPPort,
            fromMail: fromMail,
            autherMail: autherMail,
        };
        $.get("{$url}", data, function(response) {
            $(".response").html("").html(response);
        });
        
    }        
</script>
<button class="btn btn-warn" onclick="testMailConf();return false;">测试设置</button>
<pre class="response"></pre>
HTML;

        $testBtn = new Typecho_Widget_Helper_Layout();
        $testBtn->html(_t($testBtnHtml));
        $form->addItem($testBtn);
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


    /**
     * @param $comment
     * @return array
     * @throws Typecho_Db_Exception
     * 获取上级评论人
     */
    public static function getParent($comment)
    {
        $recipients = [];
        $db = Typecho_Db::get();
        $widget = Widget_Base_Comments::alloc();
        // 查询
        $select = $widget->select()->where('coid' . ' = ?', $comment->parent)->limit(1);
        $parent = $db->fetchRow($select, [$widget, 'push']); // 获取上级评论对象
        if ($parent && $parent['mail']) {
            $recipients = [
                'name' => $parent['author'],
                'mail' => $parent['mail'],
            ];
        }
        return $recipients;
    }

    /**
     * @param $comment
     * @param Widget_Comments_Edit $edit
     * @param $status
     * @throws Typecho_Db_Exception
     * @throws Typecho_Plugin_Exception
     * 在后台标记评论状态时的回调
     */
    public static function mark($comment, $edit, $status)
    {
        // 在后台标记评论状态为[approved 审核通过]时, 发信给上级评论人
        if ($status == 'approved' && 0 < $edit->parent) {
            $parent = self::getParent($edit);
            // 如果自己回复自己的评论, 不做任何操作
            if ($parent['mail'] == $edit->mail) {
                return;
            }
            $comment2Mail = Helper::options()->plugin('Comment2Mail');
            $from = $comment2Mail->autherMail; // 博主(收件)邮箱
            // 如果上级是博主, 不做任何操作
            if ($parent['mail'] == $from) {
                return;
            }
            $recipients[] = $parent;
            self::sendMail($edit, $recipients, '您有一条新的评论');
        }
    }


    /**
     * @param Widget_Comments_Edit|Widget_Feedback $comment
     * @throws Typecho_Db_Exception
     * @throws Typecho_Plugin_Exception
     * 评论/回复时的回调
     */
    public static function finishComment($comment)
    {
        $comment2Mail = Helper::options()->plugin('Comment2Mail');
        $from = $comment2Mail->from; // 发件邮箱
        $fromName = $comment2Mail->fromName; // 发件人
        $autherMail = $comment2Mail->autherMail; // 博主邮箱
        $recipients = [];
        // 审核通过
        if ($comment->status == 'approved') {
            // 不需要发信给博主
            if ($comment->authorId != $comment->ownerId && $comment->mail != $autherMail) {
                $recipients[] = [
                    'name' => $fromName,
                    'mail' => $autherMail,
                ];
            }
            // 如果有上级
            if ($comment->parent > 0) {
                // 查询上级评论人
                $parent = self::getParent($comment);
                // 如果上级是博主和自己回复自己, 不需要发信
                if ($parent['mail'] != $autherMail && $parent['mail'] != $comment->mail) {
                    $recipients[] = $parent;
                }
            }
            self::sendMail($comment, $recipients, '您有一条新的评论');
        } else {
            // 如果所有评论必须经过审核, 通知博主审核评论
            $recipients[] = ['name' => $fromName, 'mail' => $autherMail];
            self::sendMail($comment, $recipients, '您有一条新的待审核评论');
        }
    }

    /**
     * @param Widget_Comments_Edit|Widget_Feedback $comment
     * @param array $recipients
     * @param $desc
     * @throws Typecho_Plugin_Exception
     */
    private static function sendMail($comment, $recipients, $desc)
    {
        if (empty($recipients)) return; // 没有收信人
        try {
            // 获取系统配置选项
            $options = Helper::options();
            // 获取插件配置
            $comment2Mail = $options->plugin('Comment2Mail');
            $from = $comment2Mail->from; // 发件邮箱
            $fromName = $comment2Mail->fromName; // 发件人
            // Server settings
            $mail = new PHPMailer(true);
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            $mail->isSMTP();
            $mail->Host = $comment2Mail->STMPHost; // SMTP 服务地址
            $mail->SMTPAuth = true; // 开启认证
            $mail->Username = $comment2Mail->SMTPUserName; // SMTP 用户名
            $mail->Password = $comment2Mail->SMTPPassword; // SMTP 密码
            $mail->SMTPSecure = $comment2Mail->SMTPSecure; // SMTP 加密类型 'ssl' or 'tls'.
            $mail->Port = $comment2Mail->SMTPPort; // SMTP 端口

            $mail->setFrom($from, $fromName);
            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient['mail'], $recipient['name']); // 添加收件人
            }
            $mail->Subject = '来自[' . $options->title . ']站点的新消息';

            $mail->isHTML(); // 邮件为HTML格式
            // 邮件内容
            $content = self::mailBody($comment, $options, $desc);
            $mail->Body = $content;
            $mail->send();

            // 记录日志
            if ($comment2Mail->log) {
                $at = date('Y-m-d H:i:s');
                if ($mail->isError()) {
                    $data = $at . ' ' . $mail->ErrorInfo; // 记录发信失败的日志
                } else { // 记录发信成功的日志
                    $recipientNames = $recipientMails = '';
                    foreach ($recipients as $recipient) {
                        $recipientNames .= $recipient['name'] . ', ';
                        $recipientMails .= $recipient['mail'] . ', ';
                    }
                    $data  = PHP_EOL . $at .' 发送成功! ';
                    $data .= ' 发件人:'   . $fromName;
                    $data .= ' 发件邮箱:' . $from;
                    $data .= ' 接收人:'   . $recipientNames;
                    $data .= ' 接收邮箱:' . $recipientMails . PHP_EOL;
                }
                $fileName = dirname(__FILE__) . '/log.txt';
                file_put_contents($fileName, $data, FILE_APPEND);
            }

        } catch (Exception $e) {
            $fileName = dirname(__FILE__) . '/log.txt';
            $str = "\nerror time: ".date('Y-m-d H:i:s') . "\n";
            file_put_contents($fileName, $str, FILE_APPEND);
            file_put_contents($fileName, $e, FILE_APPEND);
        }
    }

    /**
     * @param $comment
     * @param $options
     * @param $desc
     * @return string
     * 很朴素的邮件风格
     */
    private static function mailBody($comment, $options, $desc)
    {
        $commentAt = new Typecho_Date($comment->created);
        $commentAt = $commentAt->format('Y-m-d H:i:s');
        $commentText = htmlspecialchars($comment->text);
        $content = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="background-color:#EEF3FA; font-size:14px;font-family:Microsoft YaHei;">
    <div style="margin:50px auto;background-color:#fff;width:700px;box-shadow: 0 0 10px #00000030;border-radius: 20px;overflow: hidden;">
		<div style="height: 70px;padding-left:28px;background-color: #37aded;box-shadow: 0 0 20px 0 #37aded;">
			<a style="cursor:pointer;font-size:28px;color:#fff;text-decoration:none;font-weight:bold;line-height:70px;" href="{$options->siteUrl}">{$options->title}</a>
			<span style="color:#fff;font-size:16px;padding-left:20px;">{$options->description}</span>
		</div>
		<div style="padding:30px;">
			<div style="height:50px;line-height:50px;font-size:16px;color:#9e9e9e;">{$desc}</div>
			<div style="line-height:30px;font-size:16px;margin-bottom:20px;text-indent:2em;">
				{$commentText}
			</div>
			<div style="line-height:40px;font-size:14px;">
				<label style="color:#999;">评论人：</label>
				<span style="color:#333;">{$comment->author}&nbsp;&nbsp;邮箱：{$comment->mail}</span>
			</div>
			<div style="line-height:40px;font-size:14px;">
				<label style="color:#999;">评论地址：</label>
				<a href="{$comment->permalink}" style="color:#333;">{$comment->permalink}</a>
			</div>
			<div style="line-height:40px;font-size:14px;">
				<label style="color:#999;">评论时间：</label>
				<span style="color:#333;">{$commentAt}</span>
			</div>
		</div>
	</div>
</body>
</html>
HTML;
        return $content;
    }

}
