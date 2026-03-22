# 68paper项目介绍
  68paper是完全开源的论文查重和AIGC检测系统。主要有如下功能。
  1. 论文查重，可以提交文件，也可以提交文本。检测完成后可以下载报告
  2. AIGC检测，可以提交文件，也可以提交文本。检测完成后可以下载报告
  3. 支付方式，支持支付宝和微信支付
  4. 支持创建多个店铺，不同的店铺可以设置不同的价格
  5. 可以在报告中加入一个pdf格式的附件，一般都用做广告

# 项目组成
  完整的68paper又三个代码仓库组成，需要至少三个域名（可以是二级域名，或者三级域名）  
  分别是 后端 、 管理前端、 售卖前端。售卖前端可以有多个，因为我们支持多个店铺、
  项目仓库：
  1. 后端  [https://github.com/88ccc/68paper-api](https://github.com/88ccc/68paper-api)
  2. 管理前端 [https://github.com/88ccc/68paper-admin](https://github.com/88ccc/68paper-admin)
  3. 售卖前端 [https://github.com/88ccc/68paper-sale](https://github.com/88ccc/68paper-sale)

# 技术框架
 后端采用ThinkPHP8, 管理前端采用Vue3, 售卖前端采用Vue3  
 数据库使用MySQL5.7  
 支付借用了 yansongda/pay  
 报告存储，支持本地存储、腾讯云COS、阿里云OSS
# 帮助文档
1、[部署帮助文档](https://rcnmk1y9ijpk.feishu.cn/wiki/R0KEwI1O6iCGF0ksnjccqvIHnPf)