# Standard Bank KYC: one web system

The Flutter app is retired. Everything now runs as one PHP web app:

| Who               | Where                    | Designed for               |
|-------------------|--------------------------|----------------------------|
| Field officers    | `/kyc/field/`            | Phones (mobile web app)    |
| Supervisors       | `/kyc/` (office dashboard) | Desktop / laptop         |
| Compliance        | `/kyc/` (office dashboard) | Desktop / laptop         |
| Admin (read-only) | `/kyc/` (office dashboard) | Desktop / laptop         |

Everyone signs in at **`/kyc/login.php`** and is sent to the right app based on their role
(`officer` goes to the field app, `supervisor` / `compliance` / `admin` go to the dashboard).

## Install
1. Copy this folder over your existing `kyc` web folder (e.g. `C:\xampp\htdocs\kyc`).
   Your `storage/kyc_uploads` folder and database are untouched. **No database changes.**
2. Delete the old `api/` folder if it is still there. It is no longer used, and it exposed customer data without a login.
3. Field officers need a `users` row with role `officer` (same as before).

## Using it on phones (office network)
1. Find the PC's IP address: `ipconfig` → IPv4 Address (e.g. `192.168.1.20`).
2. Allow Apache through Windows Firewall (XAMPP usually asks the first time).
3. On the phone (same Wi-Fi), open `http://192.168.1.20/kyc/login.php`.
4. Chrome menu → **Add to Home screen**. It then opens like an app with the Standard Bank icon.

The camera works over plain HTTP because it uses the phone's built-in camera picker.
For use outside the office network you will need HTTPS and a proper server.

## Field app (`/field`)
- **Home:** your stats (today, in review, approved, returned), 7-day activity, recent submissions, big "Start new KYC" button.
- **New KYC:** 6-step wizard: account type → customer details → address & income → documents → signature & consent → review.
  - Personal and business flows. Business adds registration number, TPIN and contact person.
  - Checks as you type: required fields, Malawi phone numbers, email, date of birth, ID number length.
  - The camera opens directly from the page (back camera for ID, front camera for the selfie), with "Choose file" for PDFs and gallery photos.
  - Photos are resized and compressed on the phone (e.g. 4 MB → about 300 KB) before upload, to save data.
  - Finger signature pad, plus a required customer consent checkbox.
  - **Auto-saved draft:** typed details, photos and the signature are kept on the phone until you submit.
    If the browser closes or the phone restarts, the officer picks up where they left off.
  - Upload with a % progress ring. On a dropped connection nothing is lost; tap Submit again.
    Retries are duplicate-safe, so a record is never saved twice.
  - The phone's back button moves between steps instead of leaving the form.
- **My KYC:** search and filter (All / In review / Approved / Returned), with "Load more".
- **Returned:** rejected KYC with the office's reason, and a **Recapture** button that pre-fills the typed details
  (photos and signature must be retaken). The office sees "Recaptured by field officer" on the new record,
  and the old record drops out of the Returned list.
- **Account:** profile and stats, photo tips, sign out.

## Office dashboard
Same as before. It now also shows recapture links in the audit trail.

## Server-side checks on every submission
- Login + role check (officers only), CSRF token
- Same validation rules as the phone (never trusts the browser)
- Files checked by content: JPG/PNG/WebP, or PDF for ID/income. Max 10 MB each
- Only writes columns that exist in your `kyc_submissions` table
- Officers can only ever see their own records

## Still to consider
- Passwords are plain text. `verifyPassword()` already accepts `password_hash()` hashes, so users can be migrated one at a time.
- For field use outside the office Wi-Fi, host it on HTTPS.
