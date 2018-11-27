# typecho-Comment2Mail

#### 项目介绍
Typecho博客评论通过时发送邮件提醒

#### 更新说明
- version 1.2.0 如果所有评论必须经过审核, 通知博主审核评论
- version 1.1.1 邮件里显示评论人邮箱
- version 1.1.0 修改了邮件样式,邮件样式是utf8,避免邮件乱码
- version 1.0.1 博主回复别人时,不需要给博主发信

#### 软件架构
- `typecho`版本为`0.8 (10.8.15)`以上
- `php: >=5.5.0`
- 如果启用SMTP加密模式`PHP`需要打开`openssl`扩展
- 邮件服务基于[`PHPMailer`](https://github.com/PHPMailer/PHPMailer/ )


#### 安装教程

- 下载本插件，解压放到`/usr/plugins/`目录中
- 文件夹名改为`Comment2Mail`
- 登录管理后台，激活插件
- 配置插件 填写SMTP参数

#### 参与贡献

1. Fork 本项目
2. 新建 Feat_xxx 分支
3. 提交代码
4. 新建 Pull Request

#### 联系作者

- Email:i@hoehub.com
- 欢迎访问 [www.hoehub.com](www.hoehub.com) 一起学习讨论

### 许可证 License

- 本项目遵循GPL-3.0开源协议发布。
- 版权所有Copyright © 2018 by Hoe (http://www.hoehub.com)
- All rights reserved。