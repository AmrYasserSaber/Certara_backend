# IRB User Story — Student/Admin Flow with Endpoints

## Purpose
This document explains the full test story from student registration until admin activation and serial generation, with concrete API endpoints and request examples.

---

## Actors
- Student
- Admin

---

## Business Story (Happy Path)
1. Student creates a new account.
2. Admin reviews pending users and activates the student.
3. Student logs in and creates research submission.
4. Student uploads required research documents.
5. Student submits research for activation workflow (`pending_activation`).
6. Admin reviews research and generates serial number.
7. Research moves to `awaiting_payment_1`.

---

## Authentication and CSRF Rules
For protected endpoints:
- Send cookie header with tokens:
  - `IRB_ACCESS_TOKEN`
  - `IRB_REFRESH_TOKEN`
  - `IRB_CSRF_TOKEN`

For state-changing endpoints (`POST`, `PUT`, `PATCH`, `DELETE`):
- Send CSRF header:
  - `X-CSRF-Token: <IRB_CSRF_TOKEN>`

---

## Phase A — Student Registration and Admin Activation

### A1) Student registers
**Endpoint**: `POST /api/auth/register`

**Request body example**
```json
{
  "name": "Student New",
  "email": "student.new@example.com",
  "password": "password123",
  "phone": "01012345678",
  "department": "Surgery",
  "faculty": "Medicine",
  "specialization": "General Surgery"
}
```

**Expected result**
- HTTP `201`
- User created with role `student`
- User status is `pending`

---

### A2) Admin logs in
**Endpoint**: `POST /api/auth/login`

**Request body example**
```json
{
  "email": "admin@irb.local",
  "password": "password"
}
```

**Expected result**
- HTTP `200`
- Auth cookies returned

---

### A3) Admin gets pending users
**Endpoint**: `GET /api/admin/users/pending?page=1&limit=20&status=pending`

**Expected result**
- New student appears in `data.items`

---

### A4) Admin activates student
**Endpoint**: `PUT /api/admin/users/{id}/activate`

**Headers (required)**
- `Cookie: IRB_ACCESS_TOKEN=...; IRB_REFRESH_TOKEN=...; IRB_CSRF_TOKEN=...`
- `X-CSRF-Token: <csrf-token>`

**Expected result**
- HTTP `200`
- User status becomes `active`

---

## Phase B — Student Research Submission

### B1) Student logs in
**Endpoint**: `POST /api/auth/login`

**Request body example**
```json
{
  "email": "student@irb.local",
  "password": "password"
}
```

**Expected result**
- HTTP `200`
- Student auth cookies returned

---

### B2) Student creates research
**Endpoint**: `POST /api/research`

**Request body example**
```json
{
  "title": "Assessment of Telemedicine Satisfaction",
  "principal_investigator": "Dr. Ahmed Ali",
  "co_investigators": "Dr. Mona Youssef",
  "department": "Family Medicine",
  "faculty": "Medicine"
}
```

**Expected result**
- HTTP `201`
- Research created (usually starts at `draft`)

---

### B3) Student uploads required documents
**Endpoint**: `POST /api/research/{id}/documents`

**Content type**: `multipart/form-data`

**Required document types (recommended)**
- protocol
- application
- coi
- checklist
- consent

**Expected result**
- HTTP `200` or `201`
- All files linked to research

---

### B4) Student submits research for admin activation stage
**Endpoint**: `PUT /api/research/{id}`

**Request body example**
```json
{
  "status": "pending_activation"
}
```

**Expected result**
- HTTP `200`
- Research status becomes `pending_activation`

---

## Phase C — Admin Approval for Serial Number

### C1) Admin views research queue
**Endpoint**: `GET /api/admin/research?status=pending_activation&page=1&limit=20`

**Expected result**
- Submitted research appears in admin queue

---

### C2) Admin generates serial number
**Endpoint**: `POST /api/admin/research/{id}/serial`

**Headers (required)**
- `Cookie: IRB_ACCESS_TOKEN=...; IRB_REFRESH_TOKEN=...; IRB_CSRF_TOKEN=...`
- `X-CSRF-Token: <csrf-token>`

**Expected result**
- HTTP `200`
- Serial number generated (example: `IRB-2026-0005`)
- Research status changes to `awaiting_payment_1`

---

### C3) Admin verifies action log
**Endpoint**: `GET /api/admin/logs?action=admin.serial_generated&page=1&limit=20`

**Expected result**
- Log item exists for the research
- Actor is admin

---

## What Student Should Do (Checklist)
1. Register account (`POST /api/auth/register`)
2. Wait for admin activation
3. Login (`POST /api/auth/login`)
4. Create research (`POST /api/research`)
5. Upload all required docs (`POST /api/research/{id}/documents`)
6. Submit to activation workflow (`PUT /api/research/{id}` → `pending_activation`)

---

## What Admin Should Do (Checklist)
1. Login (`POST /api/auth/login`)
2. Review pending users (`GET /api/admin/users/pending`)
3. Activate user (`PUT /api/admin/users/{id}/activate`)
4. Review pending research (`GET /api/admin/research?status=pending_activation`)
5. Generate serial (`POST /api/admin/research/{id}/serial`)
6. Verify logs (`GET /api/admin/logs`)

---

## Negative Test Cases
1. Student login before activation
   - Expected: `403 account_inactive`

2. Generate serial on invalid research state
   - Expected: `409 invalid_state`

3. Missing CSRF token on `PUT/POST`
   - Expected: `403 csrf_missing`

4. Student calling admin endpoints
   - Expected: `403 forbidden`

---

## Quick End-to-End Endpoint Order
1. `POST /api/auth/register`
2. `POST /api/auth/login` (admin)
3. `GET /api/admin/users/pending`
4. `PUT /api/admin/users/{id}/activate`
5. `POST /api/auth/login` (student)
6. `POST /api/research`
7. `POST /api/research/{id}/documents`
8. `PUT /api/research/{id}` (to `pending_activation`)
9. `POST /api/auth/login` (admin)
10. `GET /api/admin/research?status=pending_activation`
11. `POST /api/admin/research/{id}/serial`
12. `GET /api/admin/logs?action=admin.serial_generated`

---

## Notes
- This user story aligns with your requested student/admin workflow.

---

## Phase D — First Payment After Serial Generation

### D1) Student views research after serial is assigned
**Endpoint**: `GET /api/research/{id}`

**Expected result**
- HTTP `200`
- Research contains `serial_number`
- Research status is `awaiting_payment_1`

---

### D2) Student starts first payment
**Endpoint**: `POST /api/research/{id}/payment`

**Request body example**
```json
{
  "type": "first",
  "method": "mock",
  "amount": 250
}
```

**Expected result**
- HTTP `201` or `200`
- Payment record created
- Research moves to the next payment step when confirmed

---

### D3) Student views receipt
**Endpoint**: `GET /api/research/{id}/payment/receipt`

**Expected result**
- HTTP `200`
- Receipt data returned for PDF rendering

---

## Phase E — Sample Size Officer Workflow

### E1) Sample size officer views pending research
**Endpoint**: `GET /api/sample-size/pending?page=1&limit=20`

**Expected result**
- List shows only research with status `awaiting_sample_size`

---

### E2) Sample size officer submits sample size
**Endpoint**: `POST /api/sample-size/{research_id}`

**Request body example**
```json
{
  "calculated_size": 85,
  "notes": "Adjusted for a smaller pilot population.",
  "fee_amount": 350
}
```

**Expected result**
- HTTP `200`
- Sample size is saved
- Research status becomes `awaiting_payment_2`

---

## Phase F — Second Payment After Sample Size

### F1) Student starts second payment
**Endpoint**: `POST /api/research/{id}/payment`

**Request body example**
```json
{
  "type": "second",
  "method": "mock",
  "amount": 350
}
```

**Expected result**
- HTTP `201` or `200`
- Second payment is recorded
- Research becomes ready for review queue

---

## Phase G — Reviewer Workflow

### G1) Reviewer logs in
**Endpoint**: `POST /api/auth/login`

**Request body example**
```json
{
  "email": "reviewer@irb.local",
  "password": "password"
}
```

**Expected result**
- HTTP `200`
- Reviewer auth cookies returned

---

### G2) Reviewer views assigned research
**Endpoint**: `GET /api/reviews/assigned?page=1&limit=20`

**Expected result**
- Only assigned research is returned
- Student personal information is hidden

---

### G3) Reviewer opens research details
**Endpoint**: `GET /api/reviews/{research_id}`

**Expected result**
- Research documents and metadata returned
- No student PII exposed

---

### G4) Reviewer adds a comment
**Endpoint**: `POST /api/reviews/{research_id}/comment`

**Request body example**
```json
{
  "comment_text": "Please clarify the sample selection criteria and add more detail to the consent section."
}
```

**Expected result**
- HTTP `201` or `200`
- Comment saved to the review thread

---

### G5) Reviewer makes a decision
**Endpoint**: `PUT /api/reviews/{research_id}/decision`

**Request body example**
```json
{
  "decision": "approved"
}
```

**Other allowed values**
- `approved`
- `rejected`
- `revision_requested`

**Expected result**
- HTTP `200`
- Research status updates accordingly
- Notification is triggered

---

## Phase H — Admin Assigns Reviewer and Monitors Workflow

### H1) Admin assigns a reviewer
**Endpoint**: `PUT /api/admin/research/{id}/assign-reviewer`

**Request body example**
```json
{
  "reviewer_id": 4
}
```

**Expected result**
- HTTP `200`
- Research status becomes `in_review`
- Reviewer gets notification

---

### H2) Admin monitors logs
**Endpoint**: `GET /api/admin/logs?page=1&limit=20`

**Expected result**
- Log entries show reviewer assignment, decision, and certificate actions

---

## Phase I — Manager Final Approval

### I1) Manager views reviewed queue
**Endpoint**: `GET /api/manager/research/reviewed?page=1&limit=20`

**Expected result**
- List contains only research ready for final decision

---

### I2) Manager opens research detail
**Endpoint**: `GET /api/manager/research/{id}`

**Expected result**
- Full research detail returned
- Includes sample size, review status, documents, payment states, and certificate info

---

### I3) Manager makes final decision
**Endpoint**: `PUT /api/manager/research/{id}/decision`

**Request body example**
```json
{
  "decision": "approved",
  "note": "Approved after final review."
}
```

**Expected result**
- HTTP `200`
- Research status becomes `approved` or `rejected`
- Notification sent to student

---

### I4) Manager checks dashboard statistics
**Endpoint**: `GET /api/manager/dashboard/stats`

**Expected result**
- Response includes queue, approved, rejected, certificates, finalized, issuance_rate

---

## Phase J — Certificate Generation and Download

### J1) Manager generates certificate
**Endpoint**: `POST /api/manager/research/{id}/certificate`

**Expected result**
- HTTP `200` or `201`
- Certificate record created
- PDF file saved on server

---

### J2) Student downloads certificate
**Endpoint**: `GET /api/research/{id}/certificate`

**Expected result**
- HTTP `200`
- PDF returned as download
- Student can access only their own certificate

---

## Full End-to-End Flow Summary
1. `POST /api/auth/register`
2. `POST /api/auth/login` (admin)
3. `GET /api/admin/users/pending`
4. `PUT /api/admin/users/{id}/activate`
5. `POST /api/auth/login` (student)
6. `POST /api/research`
7. `POST /api/research/{id}/documents`
8. `PUT /api/research/{id}` → `pending_activation`
9. `GET /api/admin/research?status=pending_activation`
10. `POST /api/admin/research/{id}/serial`
11. `POST /api/research/{id}/payment` → first payment
12. `GET /api/sample-size/pending`
13. `POST /api/sample-size/{research_id}`
14. `POST /api/research/{id}/payment` → second payment
15. `POST /api/auth/login` (reviewer)
16. `GET /api/reviews/assigned`
17. `GET /api/reviews/{research_id}`
18. `POST /api/reviews/{research_id}/comment`
19. `PUT /api/reviews/{research_id}/decision`
20. `PUT /api/admin/research/{id}/assign-reviewer`
21. `GET /api/admin/logs`
22. `POST /api/auth/login` (manager)
23. `GET /api/manager/research/reviewed`
24. `GET /api/manager/research/{id}`
25. `PUT /api/manager/research/{id}/decision`
26. `POST /api/manager/research/{id}/certificate`
27. `GET /api/research/{id}/certificate`

---

## Final Notes
- This document now covers the full research lifecycle from registration to certificate download.
- It can be used as a QA checklist and as a reference for Postman collections.
