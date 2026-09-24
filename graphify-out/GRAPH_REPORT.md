# Graph Report - cathlabmanager  (2026-09-24)

## Corpus Check
- Corpus is ~13,369 words - fits in a single context window. You may not need a graph.

## Summary
- 137 nodes · 167 edges · 13 communities (9 shown, 4 thin omitted)
- Extraction: 90% EXTRACTED · 8% INFERRED · 1% AMBIGUOUS · INFERRED: 14 edges (avg confidence: 0.84)
- Token cost: 61,036 input · 0 output

## Community Hubs (Navigation)
- Admin Panel Logic
- System Features Overview
- Patient Portal & Queue Docs
- Manual Entry & Jalali Calendar
- Lab Order Management
- Tech Stack & Installation
- Database Config Helpers
- Lab Admin Panel
- Composer CI Workflow
- Prescription Submission Form
- Per-Doctor Queue Page

## God Nodes (most connected - your core abstractions)
1. `CathLab Prescription & Queue Management System` - 9 edges
2. `File Structure` - 9 edges
3. `Admin Panel` - 8 edges
4. `Technology Stack` - 8 edges
5. `Public Waiting Queue` - 7 edges
6. `renderCal()` - 6 edges
7. `build job (ubuntu-latest)` - 5 edges
8. `Patient Features (online submission, tracking, emergency request)` - 4 edges
9. `index.php (submission form + tracking)` - 4 edges
10. `submit.php (form processing)` - 4 edges

## Surprising Connections (you probably didn't know these)
- `Validate composer.json and composer.lock` --references--> `CathLab Prescription & Queue Management System`  [AMBIGUOUS]
  .github/workflows/php.yml → README.md
- `PHP Composer CI Workflow` --conceptually_related_to--> `PHP 7.4+ Backend`  [INFERRED]
  .github/workflows/php.yml → README.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Patient lifecycle from submission to completed procedure** — readme_submit_php, readme_patients_tab, readme_admitted_tab, readme_done_tab, readme_patient_workflow [INFERRED 0.85]
- **Public queue display (per-doctor, aggregate, PWA)** — readme_queue_php, readme_queue_all_php, readme_queue_manifest, readme_public_queue [EXTRACTED 1.00]
- **Composer CI pipeline steps** — _github_workflows_php_composer_validate, _github_workflows_php_composer_cache, _github_workflows_php_composer_install [EXTRACTED 1.00]

## Communities (13 total, 4 thin omitted)

### Community 0 - "Admin Panel Logic"
Cohesion: 0.09
Nodes (8): admitBtn, checkAll, handleStatusChange(), openDoneModal(), selLabel, selNum, shamsiDate(), toJalali()

### Community 1 - "System Features Overview"
Cohesion: 0.12
Nodes (19): Admin Panel, admin.php (full admin panel), Admitted (Hospitalized) Tab, CathLab Prescription & Queue Management System, Per-Doctor Statistics Dashboard, Default Admin Credentials (must change after first login), Hossein Ghalavandi (Developer), Doctors (kardooni, mohammadi, taghizadeh, hamid) (+11 more)

### Community 2 - "Patient Portal & Queue Docs"
Cohesion: 0.18
Nodes (16): Appointment Tracking by National ID or Name, Auto-refresh every 30 seconds, Chart.js, Emergency Request, File Structure, index.php (submission form + tracking), Patient Features (online submission, tracking, emergency request), Online Prescription Upload (image/PDF) (+8 more)

### Community 3 - "Manual Entry & Jalali Calendar"
Cohesion: 0.23
Nodes (12): changeMonth(), closeCal(), confirmDate(), getDaysInJMonth(), getJWeekDay(), getTodayJalali(), gToJ(), jToG() (+4 more)

### Community 4 - "Lab Order Management"
Cohesion: 0.20
Nodes (6): checkboxes, getDisplayCount(), loadData(), shamsiNow(), summary, toJalali()

### Community 5 - "Tech Stack & Installation"
Cohesion: 0.25
Nodes (9): config.php (DB connection + table creation), cPanel Shared Hosting, Custom Dark Theme, DB_HOST / DB_NAME / DB_USER / DB_PASS constants, Installation, MySQL / MariaDB via PDO, Technology Stack, HTML5 / CSS3 / Vanilla JS Frontend (+1 more)

### Community 6 - "Database Config Helpers"
Cohesion: 0.32
Nodes (4): createTablesIfNeeded(), getDB(), shamsiDate(), toJalali()

### Community 8 - "Composer CI Workflow"
Cohesion: 0.33
Nodes (7): build job (ubuntu-latest), Cache Composer packages (vendor), Install dependencies (composer install), Validate composer.json and composer.lock, PHP Composer CI Workflow, Run test suite (commented out, phpunit placeholder), PHP 7.4+ Backend

### Community 9 - "Prescription Submission Form"
Cohesion: 0.29
Nodes (4): dropArea, fileInput, fileName, preview

## Ambiguous Edges - Review These
- `build job (ubuntu-latest)` → `Run test suite (commented out, phpunit placeholder)`  [AMBIGUOUS]
  .github/workflows/php.yml · relation: references
- `Validate composer.json and composer.lock` → `CathLab Prescription & Queue Management System`  [AMBIGUOUS]
  .github/workflows/php.yml · relation: references

## Knowledge Gaps
- **20 isolated node(s):** `checkAll`, `admitBtn`, `selLabel`, `selNum`, `dropArea` (+15 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 61 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **4 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `build job (ubuntu-latest)` and `Run test suite (commented out, phpunit placeholder)`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **What is the exact relationship between `Validate composer.json and composer.lock` and `CathLab Prescription & Queue Management System`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **Why does `CathLab Prescription & Queue Management System` connect `System Features Overview` to `Composer CI Workflow`, `Patient Portal & Queue Docs`, `Tech Stack & Installation`?**
  _High betweenness centrality (0.071) - this node is a cross-community bridge._
- **Why does `Technology Stack` connect `Tech Stack & Installation` to `Composer CI Workflow`, `System Features Overview`, `Patient Portal & Queue Docs`?**
  _High betweenness centrality (0.035) - this node is a cross-community bridge._
- **What connects `checkAll`, `admitBtn`, `selLabel` to the rest of the system?**
  _20 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Admin Panel Logic` be split into smaller, more focused modules?**
  _Cohesion score 0.08666666666666667 - nodes in this community are weakly interconnected._
- **Should `System Features Overview` be split into smaller, more focused modules?**
  _Cohesion score 0.12280701754385964 - nodes in this community are weakly interconnected._