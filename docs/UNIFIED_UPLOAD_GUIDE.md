# Unified File Upload System - Usage Guide

## ✅ Migration Status

The database migration has been **successfully completed**. All required columns and indexes are in place.

## Overview

The Unified File Upload System allows you to:
- Upload documents once and analyze them multiple times
- Link documents to awards during upload
- Attach documents to events
- Select existing documents/events for award analysis (no re-upload needed)
- Track the source of each analysis (documents, events, or awards page)

## Features

### 1. Documents Page - Upload for Award Analysis

**Location:** `documents.php`

**How to use:**
1. Click the **"Upload for Award Analysis"** button (green button next to "Add Document")
2. Select an award category from the modal
3. Upload your document
4. The document will be automatically linked to the selected award category

**Benefits:**
- Document is stored in your document library
- Automatically tagged with the award category
- Can be analyzed again later without re-uploading

### 2. Events Page - Document Attachment

**Location:** `events-activities.php`

**How to use:**
1. Click **"Add Event"** button
2. Fill in event details
3. Click **"Add Supporting Document"** in the event form
4. Select an existing document from your library
5. Save the event

**Benefits:**
- Events can have supporting documents attached
- Documents attached to events can be analyzed for awards
- Maintains relationship between events and documents

### 3. Process Awards Page - Select from Existing Documents

**Location:** `user-awards.php` → "Process Award" tab

**How to use:**
1. Instead of uploading a new file, click:
   - **"Select from Documents"** - Choose from your document library
   - **"Select from Events"** - Choose from documents attached to events
2. Select a document from the modal
3. Fill in award name and description
4. Click **"Analyze Award"**

**Benefits:**
- No need to re-upload files you've already uploaded
- Analyze the same document against different awards
- Quick access to recent documents

## Workflow Examples

### Example 1: Upload Once, Analyze Multiple Times

1. Go to **Documents** page
2. Click **"Upload for Award Analysis"**
3. Select "Global Citizenship Award"
4. Upload your certificate
5. Later, go to **Process Awards** page
6. Click **"Select from Documents"**
7. Choose the same document
8. Analyze it against a different award category

### Example 2: Event with Document Analysis

1. Go to **Events & Activities** page
2. Create a new event: "International Conference 2025"
3. Click **"Add Supporting Document"**
4. Select or upload a document
5. Save the event
6. Go to **Process Awards** page
7. Click **"Select from Events"**
8. Choose the document from the event
9. Analyze it for award eligibility

### Example 3: Document Library Management

1. Upload documents to **Documents** page (regular upload)
2. Documents are stored in your library
3. Later, use them for:
   - Award analysis (via Process Awards page)
   - Event attachments (via Events page)
   - Re-analysis with different award categories

## Database Structure

### New Columns Added

**`other_documents` table:**
- `award_id` (INT, NULL) - Links document to award category
- `source_page` (ENUM: 'documents', 'events', 'awards') - Tracks where document was uploaded from

**`events` table:**
- `document_id` (INT, NULL) - Links event to a document

**`award_analysis` table:**
- `source_page` (ENUM: 'documents', 'events', 'awards') - Tracks analysis source
- `document_id` (INT, NULL) - Links analysis to original document

## API Endpoints

### Get Documents for Selection
```
GET api/get-documents.php?source=documents
GET api/get-documents.php?source=events
GET api/get-documents.php?source=all
```

### Get Award Categories
```
GET api/get-award-categories.php
```

### Upload Document with Award Link
```
POST api/other-documents.php
FormData:
  - file: [file]
  - title: "Document Title"
  - description: "Description"
  - category: "Category"
  - award_id: [optional] award category ID
  - source_page: [optional] "documents"|"events"|"awards"
```

### Create Event with Document
```
POST api/events.php?action=create
JSON:
{
  "title": "Event Title",
  "description": "Description",
  "event_date": "2025-01-15",
  "start_time": "09:00:00",
  "end_time": "17:00:00",
  "location": "Location",
  "document_id": [optional] document ID
}
```

## Troubleshooting

### Migration Issues

If you need to re-run the migration:
1. Navigate to: `http://localhost/lilac/database/run-migration.php`
2. The script will skip columns that already exist

### Verify Migration

Check migration status:
```bash
php database/verify-migration.php
```

Or visit: `http://localhost/lilac/database/verify-migration.php`

### Common Issues

**"No documents available" message:**
- Make sure you have uploaded at least one document
- Check that you're logged in with the correct user account

**"Failed to load award categories":**
- Ensure `award_categories` table has data
- Check database connection in `api/config.php`

**Document not showing in selection:**
- Verify document belongs to your user account
- Check document status is not 'deleted'

## Best Practices

1. **Organize Documents First**
   - Upload documents to Documents page first
   - Use clear, descriptive titles
   - Add descriptions for context

2. **Link During Upload**
   - Use "Upload for Award Analysis" when you know the award category
   - This creates the link immediately

3. **Re-use Documents**
   - Use "Select from Documents" instead of re-uploading
   - Saves storage space and time

4. **Event Documentation**
   - Attach documents to events when creating them
   - Makes it easy to analyze event-related documents later

## Technical Notes

- All foreign keys are set to `ON DELETE SET NULL` to preserve data integrity
- Documents can be linked to multiple awards through analysis records
- Source tracking helps maintain audit trail
- The system supports both new uploads and existing document selection

## Support

For issues or questions:
1. Check the verification script output
2. Review browser console for JavaScript errors
3. Check PHP error logs in XAMPP
4. Verify database connection settings

---

**Last Updated:** After migration completion
**Status:** ✅ Fully Operational

