CREATE TABLE user (
  user_id INT (11) PRIMARY KEY NOT NULL,
  user_username VARCHAR (255) UNIQUE NOT NULL,
  user_pass VARCHAR (255) NOT NULL,
  user_category  ENUM('Student','Professor','Secretary')
);


CREATE TABLE professor (
  professor_email VARCHAR(255) PRIMARY KEY  NOT NULL,
  professor_id INT(11),
  professor_name VARCHAR(255),
  professor_surname VARCHAR(255),
  professor_tel VARCHAR(255),
  professor_office VARCHAR(20),
  professor_department VARCHAR(50),
  professor_uni VARCHAR(50),
  professor_user_id INT(11) UNIQUE
);


CREATE TABLE student (
  student_am INT(11) PRIMARY KEY NOT NULL,
  student_name VARCHAR (255),
  student_surname VARCHAR (255),
  student_middlename VARCHAR (255),
  student_street VARCHAR (255),
  student_streetnum INT (11),
  student_city VARCHAR (255),
  student_postcode INT(11),
  student_email VARCHAR (255),
  student_tel INT(11),
  student_user_id INT(11) UNIQUE
);


CREATE TABLE secretary (
  secretary_user_id INT(11) PRIMARY KEY NOT NULL,
  secretary_name VARCHAR (255),
  secretary_surname VARCHAR (255)
);


CREATE TABLE diplo (
  diplo_id INT(11) PRIMARY KEY NOT NULL,
  diplo_title VARCHAR (255),
  diplo_desc VARCHAR (500),
  diplo_pdf VARCHAR (500) NOT NULL,
  diplo_status ENUM('active', 'cancelled' ,'under assignment' , 'finished'),
  diplo_trimelis VARCHAR (255),
  diplo_student INT(11),
  diplo_professor INT(11),
  diplo_grade DECIMAL(4,2),
  nimertis_link VARCHAR (255)
);


CREATE TABLE trimelous_invite (
  diplo_id INT(11) NOT NULL,
  diplo_student_am INT(11),
  professor_user_id INT(11),
  trimelous_date DATETIME,
  invite_status ENUM('pending','accept','deny','cancel')
);


CREATE TABLE trimelous (
  diplo_id INT(11) PRIMARY KEY NOT NULL,
  trimelous_professor1 INT(11),
  trimelous_professor2 INT(11),
  trimelous_professor3 INT(11)
);


CREATE TABLE professor_notes (
  diplo_id INT(11) ,
  professor_user_id INT(11),
  notes VARCHAR(300)
);


CREATE TABLE cancelation (
  diplo_id INT(11) NOT NULL,
  diplo_assignment_date DATETIME,
  gs_num INT(11),
  gs_year INT(11),
  cancelation_reason VARCHAR (255)
);


CREATE TABLE draft (
  diplo_id INT(11) ,
  draft_diplo_pdf VARCHAR (255),
  draft_links VARCHAR (255)
);


CREATE TABLE presentation (
  diplo_id INT(11) ,
  presentation_date DATE,
  presentation_time TIME,
  presentation_way ENUM('online','in person'),
  presentation_room VARCHAR (20),
  presentation_link VARCHAR (255)
);


CREATE TABLE grade_criteria (
  diplo_id INT(11) ,
  professor_user_id INT(11),
  quality_goals DECIMAL(4,2),
  time_interval DECIMAL(4,2),
  text_quality DECIMAL(4,2),
  Presentation DECIMAL(4,2)
);


CREATE TABLE trimelis_grades (
  diplo_id INT(11) ,
  trimelis_professor1_grade DECIMAL(4,2),
  trimelis_professor2_grade DECIMAL(4,2),
  trimelis_professor3_grade DECIMAL(4,2),
  trimelis_final_grade DECIMAL(4,2)
);

CREATE TABLE diplo_date (
  diplo_id INT(11) ,
  diplo_date DATETIME,
  diplo_status ENUM('ready','pending','cancel','under review')
);


ALTER TABLE professor
ADD CONSTRAINT fk_user_professor FOREIGN KEY (Professor_user_id) REFERENCES user(user_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE secretary 
ADD CONSTRAINT fk_user_secretary FOREIGN KEY (secretary_user_id) REFERENCES user(user_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE student
ADD CONSTRAINT fk_user_student FOREIGN KEY (student_user_id) REFERENCES user(user_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE diplo
ADD CONSTRAINT fk_diplo_professor FOREIGN KEY (diplo_professor) REFERENCES professor(professor_user_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE diplo_date
ADD CONSTRAINT fk_diplo_date FOREIGN KEY (diplo_id) REFERENCES diplo(diplo_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE cancelation
ADD CONSTRAINT fk_diplo_cancelation FOREIGN KEY (diplo_id) REFERENCES diplo(diplo_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE draft
ADD CONSTRAINT fk_diplo_draft FOREIGN KEY (diplo_id) REFERENCES diplo(diplo_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE trimelous
ADD CONSTRAINT fk_diplo_trimelous FOREIGN KEY (diplo_id) REFERENCES diplo(diplo_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE trimelis_grades
ADD CONSTRAINT fk_diplo_trimelis_grades FOREIGN KEY (diplo_id) REFERENCES diplo(diplo_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE grade_criteria
ADD CONSTRAINT fk_diplo_grade_criteria FOREIGN KEY (diplo_id) REFERENCES diplo(diplo_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE presentation
ADD CONSTRAINT fk_diplo_presentation FOREIGN KEY (diplo_id) REFERENCES diplo(diplo_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE professor_notes
ADD CONSTRAINT fk_diplo_professor_notes FOREIGN KEY (diplo_id) REFERENCES diplo(diplo_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE trimelous_invite
ADD CONSTRAINT fk_diplo_trimelous_invite FOREIGN KEY (diplo_id) REFERENCES diplo(diplo_id)
ON DELETE CASCADE
ON UPDATE CASCADE;

-- na enothi h trimelous me tin trimelous invite i me tin diplo --

INSERT INTO user (user_id, user_username, user_pass, user_category) VALUES
(30000, 'maria', 'kafk', 'Secretary'),
(10001, 'st1000001@upnet.gr', '1', 'Student'),
(10002, 'st1000002@upnet.gr', '1', 'Student'),
(10003, 'st1000003@upnet.gr', '1', 'Student'),
(10004, 'st1000004@upnet.gr', '1', 'Student'),
(10005, 'st1000005@upnet.gr', '1', 'Student'),
(10006, 'st1000006@upnet.gr', '1', 'Student'),
(10007, 'st1000007@upnet.gr', '1', 'Student'),
(10008, 'st1000008@upnet.gr', '1', 'Student'),
(10009, 'st1000009@upnet.gr', '1', 'Student'),
(10010, 'st1000010@upnet.gr', '1', 'Student'),
(10011, 'st1000011@upnet.gr', '1', 'Student'),
(10012, 'st1000012@upnet.gr', '1', 'Student'),
(10013, 'st1000013@upnet.gr', '1', 'Student'),
(10014, 'st1000014@upnet.gr', '1', 'Student'),
(10015, 'st1000015@upnet.gr', '1', 'Student'),
(10016, 'st1000016@upnet.gr', '1', 'Student'),
(10017, 'st1000017@upnet.gr', '1', 'Student'),
(10018, 'st1000018@upnet.gr', '1', 'Student'),
(10019, 'st1000019@upnet.gr', '1', 'Student'),
(10020, 'st1000020@upnet.gr', '1', 'Student'),
(20001, 'pr2000001@upnet.gr', 'admin', 'Professor'),
(20002, 'pr2000002@upnet.gr', 'admin', 'Professor'),
(20003, 'pr2000003@upnet.gr', 'admin', 'Professor'),
(20004, 'pr2000004@upnet.gr', 'admin', 'Professor'),
(20005, 'pr2000005@upnet.gr', 'admin', 'Professor'),
(20006, 'pr2000006@upnet.gr', 'admin', 'Professor'),
(20007, 'pr2000007@upnet.gr', 'admin', 'Professor'),
(20008, 'pr2000008@upnet.gr', 'admin', 'Professor'),
(20009, 'pr2000009@upnet.gr', 'admin', 'Professor'),
(20010, 'pr2000010@upnet.gr', 'admin', 'Professor'),
(20011, 'pr2000011@upnet.gr', 'admin', 'Professor'),
(20012, 'pr2000012@upnet.gr', 'admin', 'Professor'),
(20013, 'pr2000013@upnet.gr', 'admin', 'Professor'),
(20014, 'pr2000014@upnet.gr', 'admin', 'Professor'),
(20015, 'pr2000015@upnet.gr', 'admin', 'Professor'),
(20016, 'pr2000016@upnet.gr', 'admin', 'Professor'),
(20017, 'pr2000017@upnet.gr', 'admin', 'Professor'),
(20018, 'pr2000018@upnet.gr', 'admin', 'Professor'),
(20019, 'pr2000019@upnet.gr', 'admin', 'Professor'),
(20020, 'pr2000020@upnet.gr', 'admin', 'Professor')
;


INSERT INTO professor (professor_name, professor_surname, professor_tel,professor_email, professor_office, professor_department, professor_uni, professor_user_id) VALUES
('Alex', 'Ioannou', 6912345678, 'pr2000001@upnet.gr', 'A01', 'CEID', 'UPatras', 20001),
('Mike','Dionisiou',6915621568, 'pr2000002@upnet.gr', 'A02', 'CEID', 'UPatras', 20002),
('Andreas','Dionisiou',6915231568, 'pr2000003@upnet.gr', 'A03', 'CEID', 'UPatras', 20003),
('Andreas','Andreou',6911121568, 'pr2000004@upnet.gr', 'A04', 'CEID', 'UPatras', 20004),
('Mike','Dimitriou',6915627778, 'pr2000005@upnet.gr', 'A05', 'CEID', 'UPatras', 20005),
('Ioannis','Theodorou',6915621118, 'pr2000006@upnet.gr', 'A06', 'CEID', 'UPatras', 20006),
('Giorgos','Pavlou',6933331568, 'pr2000007@upnet.gr', 'A07', 'CEID', 'UPatras', 20007),
('Dimitris','Dion',6915622222, 'pr2000008@upnet.gr', 'A08', 'CEID', 'UPatras', 20008),
('Kostas','Blaxou',6900112233, 'pr2000009@upnet.gr', 'A09', 'CEID', 'UPatras', 20009),
('Saki','Rouvas',6915621500, 'pr2000010@upnet.gr', 'A10', 'CEID', 'UPatras', 20010),
('Kyriaki','Athanasiou', 6976435941, 'pr2000011@upnet.gr', 'B01', 'CEID','UPatras',20011),
('Iakovos','Alexiou', 6976437941, 'pr2000012@upnet.gr', 'B02', 'CEID','UPatras',20012),
('Tasos','Juan', 6996435941, 'pr2000013@upnet.gr', 'B03', 'CEID','UPatras',20013),
('Panayiota','Panayiotou', 6976435141, 'pr2000014@upnet.gr', 'B04', 'CEID','UPatras',20014),
('Zoe','Pittaka', 6976431241, 'pr2000015@upnet.gr', 'B05', 'CEID','UPatras',20015),
('Kostas','Evangelou', 6928435941, 'pr2000016@upnet.gr', 'B06', 'CEID','UPatras',20016),
('Ioanna','Ioannou', 6976435601, 'pr2000017@upnet.gr', 'B07', 'CEID','UPatras',20017),
('Koullis','Loutzas', 6976414741, 'pr2000018@upnet.gr', 'B08', 'CEID','UPatras',20018),
('Andreas','Charalambous', 6976694341, 'pr2000019@upnet.gr', 'B09', 'CEID','UPatras',20019),
('Katerina','Katerinaki', 69, 'pr2000020@upnet.gr', 'B10', 'CEID','UPatras',20020)
;


INSERT INTO secretary (secretary_user_id, secretary_name, secretary_surname) VALUES
(30000, 'maria', 'kafk');


INSERT INTO diplo (diplo_id, diplo_title, diplo_desc, diplo_pdf, diplo_status, diplo_student, diplo_professor, diplo_grade, nimertis_link) VALUES
(401, 'Analysi dedomenon pelatwn gia provlepsi polisewn me Python', 'dhjol', 'G:\My Drive\4ο έτος\7ο Εξάμηνο\Web\Project_26\uploads\diplo_.pdf', 'finished', 1000001, 20001, 9.0, ''),
(402, 'Συστήματα σύστασης προϊόντων με χρήση Machine Learning', 'apln', 'G:\My Drive\4ο έτος\7ο Εξάμηνο\Web\Project_26\uploads\diplo_1.pdf', 'finished', 1000002, 20001, 9.0, '' ),
(403, 'Ανίχνευση ανωμαλιών σε συναλλαγές μέσω AI', 'alpk', 'G:\My Drive\4ο έτος\7ο Εξάμηνο\Web\Project_26\uploads\diplo_2.pdf', 'cancelled', 1000003, 20004, NULL, '' ),
(404, 'Ανάλυση κοινωνικών δικτύων για προγνωστικά trends', 'askslck', 'G:\My Drive\4ο έτος\7ο Εξάμηνο\Web\Project_26\uploads\diplo_3.pdf' ,'active', 1000008, 20002, NULL, '' ),
(405, 'Κατηγοριοποίηση κειμένων με NLP', 'afwmsk', 'G:\My Drive\4ο έτος\7ο Εξάμηνο\Web\Project_26\uploads\diplo_4.pdf' , 'active', 1000007, 20004, NULL, '' ),
(406, 'Ανάλυση συναισθήματος σε σχόλια χρηστών', 'flelpk', 'G:\My Drive\4ο έτος\7ο Εξάμηνο\Web\Project_26\uploads\diplo_5.pdf', 'finished', 10000010, 20005, 10.0, '' ),
(407, 'Ανίχνευση spam emails με Deep Learning', 'kjn', 'G:\My Drive\4ο έτος\7ο Εξάμηνο\Web\Project_26\uploads\diplo_6.pdf', 'under assignment', 1000006, 20002, NULL, '' ),
(408, 'Προβλέψεις churn πελατών με Random Forest', '9', 'G:\My Drive\4ο έτος\7ο Εξάμηνο\Web\Project_26\uploads\diplo_7.pdf', 'cancelled', 1000016, 20002, NULL, '' ),
(409, 'Ανάλυση δεδομένων IoT για έξυπνα σπίτια', 'fkid', 'G:\My Drive\4ο έτος\7ο Εξάμηνο\Web\Project_26\uploads\diplo_8.pdf', 'active', 1000013, 20003, NULL, '' ),
(410, 'Ανάπτυξη responsive web app με React', 'pen', 'G:\My Drive\4ο έτος\7ο Εξάμηνο\Web\Project_26\uploads\diplo_9.pdf', 'finished', 1000011, 20007, 9.5, '' )
;

INSERT INTO presentation (diplo_id, presentation_date, presentation_time, presentation_way, presentation_room, presentation_link) VALUES
(407,"2025-10-17","11:20:00", "in person", 1 ,NULL);

INSERT INTO cancelation (diplo_id, diplo_assignment_date, gs_num, gs_year, cancelation_reason) VALUES
(402, '2025-03-14', 25, 2025, 'Άίτηση φοιτητή'),
(403, '2025-01-15', 26, 2025, 'Από διδάσκοντα')
;

INSERT INTO diplo_date (diplo_id, diplo_date, diplo_status) VALUES
(402, '2025-01-14', 'pending'),
(402, '2025-03-14', 'cancel'),
(403, '2024-10-14', 'pending'),
(402, '2025-01-15', 'cancel'),
(407, '2024-11-01', 'pending'),
(407, '2025-09-01', 'ready'),
(407, '2025-10-17', 'under review')
;

INSERT INTO grade_criteria (diplo_id, professor_user_id, quality_goals, time_interval, text_quality, Presentation) VALUES
(401, 20001, '9.0', '8.5', '9.5', '10.0'),
(401, 20011, '9.0', '9.5', '8.5', '9.0'),
(401, 20008, '9.5', '8.0', '9.0', '9.0'),
(402, 20001, '8.0', '8.5', '9.5', '9.0'),
(402, 20002, '9.0', '8.5', '6.5', '8.0'),
(402, 20010, '9.0', '9.5', '8.5', '9.0'),
(406, 20005, '7.0', '8.5', '9.5', '10.0'),
(406, 20008, '9.0', '8.5', '8.5', '10.0'),
(406, 20007, '9.0', '8.5', '7.5', '10.0'),
(410, 20007, '8.0', '9.5', '9.5', '10.0'),
(410, 20009, '10.0', '7.5', '8.5', '9.0'),
(410, 20001, '9.0', '8.5', '8.5', '9.0')
;

INSERT INTO professor_notes (diplo_id, professor_user_id, notes) VALUES
(401, 20001, 'Καλή οργάνωση και καλά αποτελέματα.'),
(402, 20001, 'Καλή μέθοδος. Εξαιρετική προσπάθεια'),
(404, 20002, 'Καλή οργάνωση και καλά αποτελέματα.'),
(405, 20004, 'Καλή οργάνωση και καλά αποτελέματα.')
;

INSERT INTO trimelous(diplo_id, trimelous_professor1,trimelous_professor2,trimelous_professor3) VALUES
(401, 20001, 20011, 20008),
(402, 20001, 20002, 20010),
(406, 20005, 20008, 20007),
(410, 20007, 20009, 20001)
;

INSERT INTO trimelis_grades (diplo_id, trimelis_professor1_grade, trimelis_professor2_grade, trimelis_professor3_grade, trimelis_final_grade) VALUES
(401, '9.3','9.0', '9.0', '9.0'),
(402, '9.0', '9.0', '9.0', '9.0'),
(406, '10.0', '9.0', '10.0', '10'),
(410, '10.0', '9.0', '8.5', '9.5')
;

INSERT INTO trimelous_invite (diplo_id, diplo_student_am, professor_user_id, trimelous_date, invite_status) VALUES
(401, 1000001, 20001, '2025-04-25', 'accept'),
(402, 1000002, 20001, '2025-04-25', 'accept'),
(404, 1000003, 20002, '2025-03-10', 'pending'),
(405, 1000007, 20004, '2025-01-01', 'deny'),
(406, 10000010, 20005, '2025-12-20', 'pending'),
(407, 1000006, 20002, '2025-03-03', 'accept'),
(409, 1000013, 20003, '2022-03-09', 'accept'),
(410, 1000011, 20007, '2025-04-17', 'accept')
;
