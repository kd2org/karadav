-- Quota is now stored in MiB, which should make things easier for 32-bits systems
UPDATE users SET quota = quota / 1024 / 1024;
