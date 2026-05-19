-- Seed 002: Freemail domain blocklist
-- Common freemail providers that should be blocked for business registration

INSERT INTO freemail_blocklist (domain, is_active, reason) VALUES
-- Major US providers
('gmail.com', TRUE, 'Consumer email service'),
('yahoo.com', TRUE, 'Consumer email service'),
('hotmail.com', TRUE, 'Consumer email service'),
('outlook.com', TRUE, 'Consumer email service'),
('live.com', TRUE, 'Consumer email service'),
('msn.com', TRUE, 'Consumer email service'),
('aol.com', TRUE, 'Consumer email service'),
('icloud.com', TRUE, 'Consumer email service'),
('me.com', TRUE, 'Consumer email service'),
('mac.com', TRUE, 'Consumer email service'),

-- European providers
('gmx.com', TRUE, 'Consumer email service'),
('gmx.de', TRUE, 'Consumer email service'),
('gmx.net', TRUE, 'Consumer email service'),
('web.de', TRUE, 'Consumer email service'),
('t-online.de', TRUE, 'Consumer email service'),
('freenet.de', TRUE, 'Consumer email service'),
('1und1.de', TRUE, 'Consumer email service'),
('1and1.com', TRUE, 'Consumer email service'),
('mail.com', TRUE, 'Consumer email service'),
('laposte.net', TRUE, 'Consumer email service'),
('orange.fr', TRUE, 'Consumer email service'),
('wanadoo.fr', TRUE, 'Consumer email service'),
('free.fr', TRUE, 'Consumer email service'),
('sfr.fr', TRUE, 'Consumer email service'),
('libero.it', TRUE, 'Consumer email service'),
('virgilio.it', TRUE, 'Consumer email service'),
('tiscali.it', TRUE, 'Consumer email service'),
('alice.it', TRUE, 'Consumer email service'),

-- Eastern European
('mail.ru', TRUE, 'Consumer email service'),
('yandex.ru', TRUE, 'Consumer email service'),
('rambler.ru', TRUE, 'Consumer email service'),
('inbox.ru', TRUE, 'Consumer email service'),
('bk.ru', TRUE, 'Consumer email service'),
('list.ru', TRUE, 'Consumer email service'),

-- Asian providers
('qq.com', TRUE, 'Consumer email service'),
('163.com', TRUE, 'Consumer email service'),
('126.com', TRUE, 'Consumer email service'),
('sina.com', TRUE, 'Consumer email service'),
('sohu.com', TRUE, 'Consumer email service'),
('naver.com', TRUE, 'Consumer email service'),
('daum.net', TRUE, 'Consumer email service'),
('hanmail.net', TRUE, 'Consumer email service'),

-- Other international
('terra.com.br', TRUE, 'Consumer email service'),
('uol.com.br', TRUE, 'Consumer email service'),
('ig.com.br', TRUE, 'Consumer email service'),
('globo.com', TRUE, 'Consumer email service'),
('bol.com.br', TRUE, 'Consumer email service'),

-- Temporary/disposable email providers
('10minutemail.com', TRUE, 'Temporary email service'),
('guerrillamail.com', TRUE, 'Temporary email service'),
('mailinator.com', TRUE, 'Temporary email service'),
('tempmail.org', TRUE, 'Temporary email service'),
('temp-mail.org', TRUE, 'Temporary email service'),
('throwaway.email', TRUE, 'Temporary email service'),
('maildrop.cc', TRUE, 'Temporary email service'),
('getnada.com', TRUE, 'Temporary email service'),

-- Legacy/deprecated domains
('hotmail.co.uk', TRUE, 'Legacy consumer email service'),
('hotmail.de', TRUE, 'Legacy consumer email service'),
('hotmail.fr', TRUE, 'Legacy consumer email service'),
('hotmail.it', TRUE, 'Legacy consumer email service'),
('hotmail.es', TRUE, 'Legacy consumer email service'),
('live.de', TRUE, 'Legacy consumer email service'),
('live.fr', TRUE, 'Legacy consumer email service'),
('live.it', TRUE, 'Legacy consumer email service'),
('live.co.uk', TRUE, 'Legacy consumer email service');