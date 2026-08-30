CREATE TABLE `alert_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `website_id` int(11) NOT NULL,
  `alert_type` enum('http_down','http_recovery','ssl_warning','ssl_expired','ssl_recovery') COLLATE utf8mb4_unicode_ci NOT NULL,
  `alert_message` text COLLATE utf8mb4_unicode_ci,
  `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_website_id` (`website_id`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `alert_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(64) NOT NULL,
  `setting_value` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `alert_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_type` enum('email','telegram') NOT NULL,
  `alert_type` enum('http_down','http_up','ssl_warning','whois_warning','all') NOT NULL DEFAULT 'all',
  `template_name` varchar(50) NOT NULL,
  `subject_template` text,
  `body_template` text NOT NULL,
  `enabled` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `email_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) DEFAULT '0',
  `smtp_host` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'smtp.163.com',
  `smtp_port` smallint(5) unsigned NOT NULL DEFAULT '465',
  `smtp_secure` enum('ssl','tls') COLLATE utf8mb4_unicode_ci DEFAULT 'ssl',
  `smtp_username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `smtp_password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '网站监控系统',
  `to_emails` text COLLATE utf8mb4_unicode_ci,
  `last_test` timestamp NULL DEFAULT NULL,
  `test_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enabled` (`enabled`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `email_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `website_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alert_type` enum('http_down','ssl_warning','ssl_expired','test','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipients` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('success','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'success',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_sent_at` (`sent_at`) USING BTREE,
  KEY `idx_alert_type` (`alert_type`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `gold_price_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metal` varchar(20) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'CNY',
  `display_name` varchar(50) NOT NULL,
  `sort_order` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `metal` (`metal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `gold_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metal` varchar(20) NOT NULL COMMENT 'gold/silver/platinum',
  `currency` varchar(10) NOT NULL DEFAULT 'CNY',
  `price` decimal(12,4) NOT NULL,
  `unit` varchar(10) NOT NULL COMMENT 'oz/g',
  `fetched_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_metal_currency` (`metal`,`currency`),
  KEY `idx_fetched_at` (`fetched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `monitor_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `website_id` int(10) unsigned NOT NULL,
  `node_id` int(11) DEFAULT NULL COMMENT '检测节点ID',
  `check_type` enum('http','ssl','both') COLLATE utf8mb4_unicode_ci DEFAULT 'both',
  `http_status` enum('up','down','unknown') COLLATE utf8mb4_unicode_ci DEFAULT 'unknown',
  `http_code` smallint(5) unsigned DEFAULT NULL,
  `http_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_time` smallint(5) unsigned DEFAULT NULL,
  `ssl_status` enum('valid','warning','expired','invalid','unknown') COLLATE utf8mb4_unicode_ci DEFAULT 'unknown',
  `ssl_days` smallint(6) DEFAULT NULL,
  `ssl_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_website_id_checked_at` (`website_id`,`checked_at`) USING BTREE,
  KEY `idx_checked_at` (`checked_at`) USING BTREE,
  KEY `idx_http_status` (`http_status`) USING BTREE,
  KEY `idx_ssl_status` (`ssl_status`) USING BTREE,
  KEY `idx_website_status` (`website_id`,`http_status`,`ssl_status`) USING BTREE,
  CONSTRAINT `monitor_logs_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `monitor_nodes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '未知',
  `api_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode` enum('pull','push') COLLATE utf8mb4_unicode_ci DEFAULT 'pull',
  `enabled` tinyint(1) DEFAULT '1',
  `last_heartbeat` timestamp NULL DEFAULT NULL,
  `status` enum('online','offline','unknown') COLLATE utf8mb4_unicode_ci DEFAULT 'unknown',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enabled` (`enabled`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `node_check_times` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `node_id` int(11) NOT NULL,
  `website_id` int(11) NOT NULL,
  `last_check_time` datetime DEFAULT NULL,
  `check_period` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_node_website` (`node_id`,`website_id`),
  KEY `idx_node_id` (`node_id`),
  KEY `idx_website_id` (`website_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `node_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `node_id` int(11) NOT NULL,
  `website_id` int(11) NOT NULL,
  `http_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `http_code` smallint(6) DEFAULT NULL,
  `ssl_days` smallint(6) DEFAULT NULL,
  `response_time` int(11) DEFAULT NULL,
  `report_data` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `processed` tinyint(4) DEFAULT '0',
  `processed_at` datetime DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_node_created` (`node_id`,`created_at`),
  KEY `idx_processed` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `nodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '节点名称',
  `node_key` varchar(64) DEFAULT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型: 1=Pull, 2=Push',
  `url` varchar(255) DEFAULT NULL COMMENT 'Pull模式探针地址',
  `api_key` varchar(64) NOT NULL COMMENT '通信密钥',
  `global_key` varchar(64) DEFAULT NULL COMMENT '全局通信密钥',
  `ip_address` varchar(50) DEFAULT NULL COMMENT '节点IP',
  `location` varchar(100) DEFAULT NULL COMMENT '归属地',
  `last_heartbeat` datetime DEFAULT NULL COMMENT '最后心跳时间',
  `last_ip` varchar(45) DEFAULT NULL,
  `ip_location` varchar(100) DEFAULT NULL,
  `status` enum('online','offline','unknown') DEFAULT 'unknown' COMMENT '状态',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `enabled` tinyint(1) DEFAULT '1' COMMENT '是否启用',
  `use_global_key` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`),
  UNIQUE KEY `node_key` (`node_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='监控节点表';

CREATE TABLE `notification_channels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_type` varchar(50) NOT NULL COMMENT '渠道类型: email, telegram, sms等',
  `channel_name` varchar(100) NOT NULL COMMENT '渠道名称',
  `enabled` tinyint(1) DEFAULT '1' COMMENT '是否启用',
  `priority` int(11) DEFAULT '0' COMMENT '优先级',
  `config_table` varchar(100) DEFAULT NULL COMMENT '配置表名',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知渠道管理';

CREATE TABLE `system_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `monitor_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `global_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `check_interval` smallint(5) unsigned DEFAULT '60',
  `ssl_warning_days` tinyint(3) unsigned DEFAULT '7',
  `history_retention_days` smallint(5) unsigned DEFAULT '30',
  `timeout_seconds` tinyint(3) unsigned DEFAULT '10',
  `last_check` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `concurrent_workers` int(11) DEFAULT '10',
  `http_timeout` int(11) DEFAULT '10',
  `ssl_timeout` int(11) DEFAULT '10',
  `alert_cooldown_hours` int(11) DEFAULT '6',
  `ssl_alert_cooldown_hours` int(11) DEFAULT '24',
  `ssl_check_interval_hours` int(11) DEFAULT '24',
  `current_theme` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'apple',
  PRIMARY KEY (`id`),
  KEY `idx_monitor_key` (`monitor_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `telegram_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bot_token` varchar(255) NOT NULL COMMENT '机器人Token',
  `chat_id` varchar(100) NOT NULL COMMENT '聊天ID',
  `enabled` tinyint(1) DEFAULT '1' COMMENT '是否启用',
  `message_template` text COMMENT '消息模板',
  `parse_mode` varchar(20) DEFAULT 'HTML' COMMENT '解析模式',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Telegram通知配置';

CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


CREATE TABLE `websites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `host` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `node_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `check_http` tinyint(1) DEFAULT '1',
  `check_ssl` tinyint(1) DEFAULT '1',
  `check_whois` tinyint(1) DEFAULT '1',
  `enabled` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_http_alert` datetime DEFAULT NULL,
  `last_ssl_alert` datetime DEFAULT NULL,
  `http_alert_count` int(11) DEFAULT '0',
  `ssl_alert_count` int(11) DEFAULT '0',
  `last_recovery_notice` datetime DEFAULT NULL,
  `last_http_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '上次HTTP状态',
  `last_ssl_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '上次SSL状态',
  `last_ssl_days` int(11) DEFAULT NULL COMMENT '上次SSL剩余天数',
  `last_ssl_alert_date` date DEFAULT NULL COMMENT '上次SSL告警日期',
  `last_check_time` datetime DEFAULT NULL COMMENT '最后检查时间',
  `last_multi_check_time` datetime DEFAULT NULL,
  `multi_sync_count` int(11) DEFAULT '0',
  `multi_sync_total` int(11) DEFAULT '0',
  `last_response_time` int(11) DEFAULT NULL,
  `check_interval` int(11) DEFAULT '5' COMMENT '检测频率（分钟）',
  `whois_days` int(11) DEFAULT NULL COMMENT '域名到期剩余天数',
  `whois_expire_date` date DEFAULT NULL COMMENT '域名到期日期',
  `last_whois_check` datetime DEFAULT NULL COMMENT '上次WHOIS检测时间',
  `last_whois_alert` datetime DEFAULT NULL COMMENT '上次域名告警时间',
  `whois_alert_count` int(11) DEFAULT '0' COMMENT '域名告警次数',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_host` (`host`) USING BTREE,
  KEY `idx_host` (`host`) USING BTREE,
  KEY `idx_enabled` (`enabled`) USING BTREE,
  KEY `idx_created_at` (`created_at`) USING BTREE,
  KEY `idx_last_http_alert` (`last_http_alert`),
  KEY `idx_last_ssl_alert` (`last_ssl_alert`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

