# Security migration: modern password hashes and login throttling.
#
# Safe to run while the app is online, and safe to run more than once.
# Back up the database first. After users log in on the new image, their hashes
# are upgraded and older images can no longer verify them. To roll back to an
# older image, restore tt_users.password from that backup.

ALTER TABLE `tt_users` MODIFY `password` varchar(255) default NULL;

CREATE TABLE IF NOT EXISTS `tt_login_attempts` (
  `login_hash` char(64) NOT NULL,
  `created` datetime NOT NULL,
  KEY `login_attempts_idx` (`login_hash`, `created`)
);
