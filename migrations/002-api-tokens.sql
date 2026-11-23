# API support: personal access tokens, retry protection, and an audit trail.
#
# Safe to run while the app is online, and safe to run more than once.
# It only adds new tables, so older images keep working.

CREATE TABLE IF NOT EXISTS `tt_api_tokens` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `token_prefix` char(14) NOT NULL,
  `scopes` varchar(64) NOT NULL,
  `expires` datetime NOT NULL,
  `last_used` datetime default NULL,
  `created` datetime NOT NULL,
  `revoked` datetime default NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_token_hash_idx` (`token_hash`),
  KEY `api_token_user_idx` (`user_id`)
);

CREATE TABLE IF NOT EXISTS `tt_api_idempotency` (
  `token_id` int(11) NOT NULL,
  `key_hash` char(64) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `entry_id` bigint default NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`token_id`, `key_hash`),
  KEY `api_idempotency_created_idx` (`created`)
);

CREATE TABLE IF NOT EXISTS `tt_api_audit` (
  `id` bigint NOT NULL auto_increment,
  `token_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(16) NOT NULL,
  `entry_id` bigint NOT NULL,
  `before_json` text,
  `after_json` text,
  `ip` varchar(45) default NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `api_audit_entry_idx` (`entry_id`),
  KEY `api_audit_token_idx` (`token_id`)
);
