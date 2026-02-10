# OnRoute Courier Booking - Version 1.8.0 Update Guide

## 🎉 What's New in v1.8.0

### Major Changes

#### 1. **Status Terminology Update**

**OLD Status Flow:**

- pending → confirmed → collected → in_transit → delivered → completed

**NEW Status Flow:**

- **booked** → **picked_up** → in_transit → delivered → completed

**Customer-Facing Labels:**

- ✅ "Booked" (instead of "Pending" or "Confirmed")
- ✅ "Picked up" (instead of "Collected")
- ✅ "In Transit", "Delivered", "Completed", "Cancelled" (unchanged)

**Why?** Customer research showed "picked up" is clearer than "collected" and "booked" is more intuitive than "pending/confirmed".

---

#### 2. **Download POD Feature**

Customers can now **download Proof of Delivery** as a printable HTML document.

**How to Use:**

1. Go to **My Bookings** → Click **View** on any delivered booking
2. In Job Detail view, click **Download POD** button (green button with download icon)
3. New window opens with formatted POD document containing:
   - Booking details
   - Pickup confirmation with signature
   - Delivery confirmation with signature
   - Auto-prints on load (customer can save as PDF via browser)

---

#### 3. **Enhanced Dashboard Features**

- 7-tab Business Credit dashboard
- Job detail view with full POD visibility
- Editable account settings
- Saved locations with full CRUD
- Support ticket system
- Auto-generated invoices

---

## 🔧 Database Migration Required

**⚠️ IMPORTANT:** Before using this plugin in production, you MUST run the database migration to update existing booking statuses.

### Option 1: Via phpMyAdmin (Recommended)

1. Open phpMyAdmin
2. Select your WordPress database
3. Click "SQL" tab
4. Copy and paste contents of `MIGRATION_STATUS_TERMS.sql`
5. Click "Go"
6. Verify results

### Option 2: Via MySQL Command Line

```bash
mysql -u your_username -p your_database_name < MIGRATION_STATUS_TERMS.sql
```

### What the Migration Does:

- Updates all `pending` bookings → `booked`
- Updates all `confirmed` bookings → `booked`
- Updates all `collected` bookings → `picked_up`
- All other statuses remain unchanged

**Expected Results:**

```sql
SELECT status, COUNT(*) FROM wp_ocb_bookings GROUP BY status;
```

Should show: `booked`, `picked_up`, `in_transit`, `delivered`, `completed`, `cancelled`

---

## 📊 Feature Overview

### Customer Dashboard Tabs

| Tab                 | Features                                                 |
| ------------------- | -------------------------------------------------------- |
| **Overview**        | Account balance, pending bookings count, total delivered |
| **New Booking**     | Multi-step booking wizard with address lookup            |
| **My Bookings**     | Table of all bookings with View/Print buttons            |
| **Job Detail**      | Full booking info, POD with signatures, Download POD     |
| **Invoices**        | Auto-generated invoices (printable)                      |
| **Saved Locations** | Save frequent pickup/delivery addresses                  |
| **Support**         | Create support tickets with file uploads                 |
| **Settings**        | Edit company name, phone, address, change password       |

### Admin Features

| Page                | Features                                                                 |
| ------------------- | ------------------------------------------------------------------------ |
| **Job Management**  | View all jobs, enter POD (pickup/delivery), change status, resend emails |
| **Support Tickets** | View tickets, reply, close tickets                                       |

---

## 🚀 Quick Start for Admins

### Entering Proof of Delivery

1. Go to **OnRoute Dashboard → Job Management**
2. Find the booking (use filters if needed)
3. Click job reference to open detail view
4. Scroll to **Proof of Pickup** or **Proof of Delivery** section
5. Fill in:
   - Person name (who handed/received the parcel)
   - Upload signature image OR paste signature data URL
6. Click **Save Pickup POD** or **Save Delivery POD**
7. ✅ Confirmation email automatically sent to customer
8. ✅ Invoice automatically generated (if not already generated)

**Status Changes:**

- Saving **Pickup POD** changes status to: `picked_up`
- Saving **Delivery POD** changes status to: `delivered`

---

## 📧 Email Workflow

| Event                | Email Sent            | Recipients |
| -------------------- | --------------------- | ---------- |
| New booking created  | Booking Confirmation  | Customer   |
| Pickup POD entered   | Pickup Confirmation   | Customer   |
| Delivery POD entered | Delivery Confirmation | Customer   |

All emails use OnRoute branding and include job reference, addresses, and POD details.

---

## 🎨 Status Colors Reference

**Dashboard Status Pills:**

- 🟡 **Booked** - Yellow badge (new booking, awaiting pickup)
- 🔵 **Picked up** - Cyan badge (collected, ready for delivery)
- 🔵 **In Transit** - Blue badge (on the way to delivery)
- 🟢 **Delivered** - Green badge (delivered, POD recorded)
- 🟢 **Completed** - Green badge (finalized)
- 🔴 **Cancelled** - Red badge (cancelled booking)

---

## 💡 Customer Communication Tips

### When Launching v1.8.0

**Email Template for Customers:**

```
Subject: OnRoute Couriers - New Dashboard Features!

Hi [Customer Name],

We've upgraded our customer dashboard with exciting new features:

✅ Download your Proof of Delivery documents
✅ Clearer status updates ("Booked" → "Picked up" → "Delivered")
✅ Saved locations for faster booking
✅ Support ticket system for easier communication
✅ Automatic invoice generation

Log in to your dashboard to explore: [Dashboard URL]

Questions? Use our new Support tab in your dashboard!

Best regards,
OnRoute Couriers Team
```

---

## 🔒 Security Checklist

Before going live:

- [ ] Test all AJAX endpoints (check nonces are working)
- [ ] Test file upload limits (support attachments, POD signatures)
- [ ] Review user capability checks (admin-only functions)
- [ ] Test password change functionality
- [ ] Verify email delivery (check spam folders)
- [ ] Test on mobile devices

---

## 🐛 Troubleshooting

### Issue: Old statuses still showing

**Solution:** Run `MIGRATION_STATUS_TERMS.sql` database migration

### Issue: Download POD button not appearing

**Cause:** Button only shows if pickup or delivery POD exists
**Solution:** Enter POD via Job Management first

### Issue: Invoices not auto-generating

**Cause:** Status might still be old "pending"
**Solution:** Run database migration, or manually change status to "booked"

### Issue: Emails not sending

**Check:**

1. SMTP settings in plugin settings
2. Gmail credentials (ops@onroutecouriers.com)
3. Server firewall allows SMTP traffic
4. Check spam folders

### Issue: POD signatures not uploading

**Check:**

1. PHP `upload_max_filesize` setting
2. WordPress media upload limits
3. File type is image (jpg, png, gif)
4. File size under 2MB

---

## 📞 Support

For technical issues with this plugin:

- Create a support ticket via the dashboard
- Email: ops@onroutecouriers.com
- Check `PRODUCTION_READINESS_AUDIT.md` for detailed feature documentation

---

**Plugin Version:** 1.8.0  
**Last Updated:** February 2025  
**Maintained By:** OnRoute Courier Booking Development Team
