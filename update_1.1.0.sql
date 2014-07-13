DROP TABLE IF EXISTS wbb1_board_icon;
CREATE TABLE wbb1_board_icon (
	iconID INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	title VARCHAR(255) NOT NULL,
	fileExtension VARCHAR(7) NOT NULL DEFAULT '',
	fileHash VARCHAR(40) NOT NULL DEFAULT '',
	filesize INT(10) NOT NULL DEFAULT 0
);