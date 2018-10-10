<?php

/**
 * typecho 评论邮件提醒
 * @package Comment2Mail
 * @author Hoe
 * @version 1.0.0
 * @link http://www.hoehub.com
 */
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
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = [__CLASS__, 'sendMail'];
        Typecho_Plugin::factory('Widget_Comments_Edit')->finishComment = [__CLASS__, 'sendMail'];
        return _t('请配置邮箱STMP选项!');
    }

    public function sendMail($comment)
    {
        file_put_contents(dirname(__FILE__) . '/log.txt', $comment, FILE_APPEND);
        file_put_contents(dirname(__FILE__) . '/log.txt', 222, FILE_APPEND);
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
     * @throws Typecho_Plugin_Exception
     */
    public static function render()
    {

    }

}
