-- ================================================================================
-- OnRoute Courier Booking - Status Terminology Migration
-- Version: 1.8.0
-- Date: 2025
-- ================================================================================
--
-- This script updates old status values to new customer-friendly terminology:
--   - "pending" → "booked" (default new booking status)
--   - "confirmed" → "booked" (consolidated with pending, as both mean "booked")
--   - "collected" → "picked_up" (clearer customer terminology)
--
--  All other statuses remain unchanged: in_transit, delivered, completed, cancelled
--
-- ================================================================================

-- BACKUP FIRST! Always backup your database before running this migration.
-- Run this via phpMyAdmin or MySQL command line.

-- Update pending bookings to "booked"
UPDATE wp_ocb_bookings 
SET status = 'booked' 
WHERE status = 'pending';

-- Update confirmed bookings to "booked"  
UPDATE wp_ocb_bookings 
SET status = 'booked' 
WHERE status = 'confirmed';

-- Update collected bookings to "picked_up"
UPDATE wp_ocb_bookings 
SET status = 'picked_up' 
WHERE status = 'collected';

-- Verify results
SELECT status, COUNT(*) as count 
FROM wp_ocb_bookings 
GROUP BY status 
ORDER BY status;

-- ================================================================================
-- Expected statuses after migration:
-- - booked (new bookings)
-- - picked_up (after collection POD)
-- - in_transit (in delivery)
-- - delivered (after delivery POD)
-- - completed (final confirmation)
-- - cancelled (cancelled bookings)
-- ================================================================================
