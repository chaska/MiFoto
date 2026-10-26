-- Uninstalling does NOT delete the data on purpose: the tables #__mifoto_photos and #__mifoto_limits stay, so reinstalling
-- or switching versions keeps every photo. The files in images/MiFoto stay too. To wipe everything by hand:
--   DROP TABLE "#__mifoto_photos"; DROP TABLE "#__mifoto_limits";   and delete the images/MiFoto folder.
SELECT 1;
