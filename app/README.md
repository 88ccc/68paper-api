基于 ThinkPHP 8  php8.4  需要php扩展 zip bcmath


CREATE TABLE `pt_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tid` int(10) unsigned DEFAULT '0' COMMENT '推荐人ID',
  `name` varchar(50) DEFAULT NULL COMMENT '昵称',
  `email` varchar(60) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `avatar` varchar(100) DEFAULT NULL COMMENT '用户头像',
  `pass` varchar(100) NOT NULL,
  `apikey` varchar(100) DEFAULT NULL,
  `api_status` int(11) DEFAULT '1' COMMENT '1允许,0不允许',
  `ip_list` varchar(200) DEFAULT NULL COMMENT '白名单用;隔开',
  `openid` varchar(128) DEFAULT NULL COMMENT '微信公众号',
  `status` tinyint(3) unsigned DEFAULT '1' COMMENT '0待激活,1正常,2冻结',
  `regtime` datetime DEFAULT NULL COMMENT '注册时间',
  `logintime` datetime DEFAULT NULL COMMENT '登录时间',
  `balance` int(11) DEFAULT '0' COMMENT '余额包含赠送',
  `gift` int(11) DEFAULT '0' COMMENT '赠送余额',
  `status_time` datetime DEFAULT NULL,
  `tips` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8


CREATE TABLE `pt_admin` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL COMMENT '昵称',
  `email` varchar(60) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `avatar` varchar(100) DEFAULT NULL COMMENT '用户头像',
  `pass` varchar(100) NOT NULL,
  `openid` varchar(128) DEFAULT NULL COMMENT '微信公众号',
  `status` tinyint(3) unsigned DEFAULT '1' COMMENT '0待激活,1正常,2冻结',
  `regtime` datetime DEFAULT NULL COMMENT '注册时间',
  `logintime` datetime DEFAULT NULL COMMENT '登录时间',
  `status_time` datetime DEFAULT NULL,
  `tips` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8


CREATE TABLE `pt_config` (
  `key` varchar(50) NOT NULL COMMENT '配置键名',
  `value` varchar(500) COMMENT '配置值',
  `title` varchar(100) NOT NULL COMMENT '配置名称',
  `type` varchar(20) DEFAULT 'char' COMMENT '配置类型',
  `updated_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

CREATE TABLE pt_article (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL COMMENT '文章标题',
  `content` MEDIUMTEXT NOT NULL COMMENT '文章内容',
  `updated_time` datetime DEFAULT NULL COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4  COMMENT='文章列表';
