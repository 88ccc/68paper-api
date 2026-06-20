# 68paper项目介绍
  68paper是完全开源的论文查重和AIGC检测系统。主要有如下功能。
  1. 论文查重，可以提交文件，检测完成后可以下载报告
  2. AIGC检测，可以提交文件，检测完成后可以下载报告
  3. 支付方式，支持支付宝和微信支付
  4. 支持代理注册，这是一个典型的分销平台
  5. 可以在报告中加入一个pdf格式的附件，一般都用做广告

# 演示网站
   1、管理端 [https://admin.68paper.com/super](https://admin.68paper.com/super)
      账号：admin 密码：123456
   2、代理注册端 [https://check.68paper.com](https://check.68paper.com)
      账号：自己注册

# 项目组成
  完整的68paper有三个代码仓库组成，需要至少三个域名（可以是二级域名，或者三级域名）  
  分别是 后端 、 管理前端、 售卖前端。
  项目仓库：
  1. 后端  
  github仓库 [https://github.com/88ccc/68paper-api.git](https://github.com/88ccc/68paper-api.git)  
  gitee仓库 [https://gitee.com/su88/68paper-api.git](https://github.com/88ccc/68paper-api.git)
  2. 管理前端  
  github仓库 [https://github.com/88ccc/68paper-admin.git](https://github.com/88ccc/68paper-admin.git)  
  gitee仓库 [https://gitee.com/su88/68paper-admin.git](https://gitee.com/su88/68paper-admin.git)
  3. 售卖前端  
  github仓库 [https://github.com/88ccc/68paper-check.git](https://github.com/88ccc/68paper-check.git)  
  gitee仓库  [https://gitee.com/su88/68paper-check.git](https://gitee.com/su88/68paper-check.git)

# 技术框架
 后端采用ThinkPHP8, 管理前端采用Vue3 element-plus, 售卖前端采用Vue3 element-plus 
 数据库使用MySQL5.7  
 支付借用了 yansongda/pay  
 报告存储，支持本地存储、腾讯云COS、阿里云OSS
# 帮助文档
1、[部署帮助文档](https://rcnmk1y9ijpk.feishu.cn/wiki/GDO9wHoSbiQuOBk4mNtcAxtUnKe)
# QQ交流群
1、68paper交流群：1073220023
  如果不会部署，可以加群询问，我会帮助部署。