# Portal agent test run

Base URL: http://127.0.0.1:8000
DB driver: sqlite
Seed present: yes (TH101=01M1WMAJE0QDDBPXC9Z5HDVAZZ)
Ran at: 2026-09-07T00:30:50+00:00

## Wave 1

Case | Result | Evidence
---|---|---
D1 | **PASS** | home 200 cta=yes
D2 | **PASS** | demo 200 featured=yes leak=no
D3 | **PASS** | seed modal markup
D4 | **PASS** | reset modal markup
D6 | **PASS** | enter John loc=http://127.0.0.1:8000/learn/01M1WMAJE0QDDBPXC9Z5HDVAZZ land=200
D7 | **PASS** | switch loc=http://127.0.0.1:8000/teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ land=200
D8 | **PASS** | superadmin=404 nobody=404
D9 | **PASS** | ar status=200 rtl=yes
D10 | **PASS** | fr heading=yes
D0 | **PASS** | covered by DemoConsoleTest disabled_console_is_hidden_and_not_found

## Wave 2

Case | Result | Evidence
---|---|---
G1 | **PASS** | landing 200
G2 | **PASS** | catalog 200 th101=yes
G3 | **PASS** | preview 200
G4 | **PASS** | register 200
G5 | **PASS** | login 200
G6 | **PASS** | health=200 up=200
G7 | **PASS** | branding 200
G8 | **PASS** | layout wrap covered by CSS + prior screenshots; HTTP N/A

## Wave 3

Case | Result | Evidence
---|---|---
S1 | **PASS** | learn 200
S2 | **PASS** | dash 200
S3 | **FAIL** | /projects/mine=404
S4 | **PASS** | player 200
S5 | **PASS** | item 200
S6 | **PASS** | ann 200
S7 | **PASS** | finance 200
S8 | **PASS** | invoice 200
S9 | **PASS** | attendance 200
S10 | **PASS** | enrollments 200
S11 | **PASS** | applications 200
S12 | **PASS** | grades 200
S13 | **PASS** | surveys 200
S15 | **PASS** | events 200
S17 | **PASS** | live-quiz join 200
S18 | **PASS** | projects 200
S19 | **PASS** | settings=200 notif=200 transcript=200 completion=200
S20 | **PASS** | hannah dash 200 rtl=yes
S21 | **PASS** | learn=200 att=200
S22 | **PASS** | bottom nav present

## Wave 4

Case | Result | Evidence
---|---|---
T1 | **PASS** | mina land 200 loc=http://127.0.0.1:8000/teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ
T2 | **PASS** | teach index 200
T3 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ 200
T4 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ?tab=assessments 200
T5 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ/assignments 200
T6 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ?tab=gradebook 200
T7 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ/live 200
T8 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ/attendance 200
T9 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ/discussions 200
T10 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ?tab=announcements 200
T11 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ?tab=roster 200
T13 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ/completion 200
T14 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ/projects 200
T15 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ/live-quiz 200
T16 | **PASS** | /teach/01M1WMAJE0QDDBPXC9Z5HDVAZZ/surveys 200
T12 | **PASS** | dossier 200
T17 | **PASS** | ta teach=200 offering=200 completion=200
T18 | **FAIL** | mina admin/programs 200
T19 | **PASS** | mobile teach tabs — CSS overflow-auto present in workspace tabs

## Wave 5

Case | Result | Evidence
---|---|---
C1 | **PASS** | aca programs 200
C2 | **PASS** | program show
C3 | **PASS** | courses 200
C4 | **PASS** | offerings 200
C5 | **FAIL** | /admin/communications/report=404
C6 | **PASS** | aca users 403
O1 | **FAIL** | adm applications 0
O2 | **PASS** | users 200
O3 | **FAIL** | /admin/communications/report=404
O4 | **PASS** | theme 200
O5 | **FAIL** | adm programs 200
O6 | **FAIL** | adm finance 200
F1 | **FAIL** | fin admin 0 loc=
F2 | **PASS** | hub=200 reports=200
F3 | **PASS** | own finance 200
F4 | **FAIL** | fin applications 200
F5 | **FAIL** | finance html 0

## Wave 6

Case | Result | Evidence
---|---|---
P1 | **FAIL** | dual loc= land=0 name=no
P2 | **FAIL** | ins2 loc= land=0 name=no
P3 | **FAIL** | student2 loc= land=0 name=no
P4 | **FAIL** | student3 loc= land=0 name=no
P5 | **FAIL** | student4 loc= land=0 name=no
P6 | **FAIL** | student5 loc= land=0 name=no
P7 | **FAIL** | student7 loc= land=0 name=no
P8 | **FAIL** | student8 loc= land=0 name=no
P9 | **FAIL** | student9 loc= land=0 name=no
P10 | **FAIL** | student10 loc= land=0 name=no
P1b | **FAIL** | dual teach=200 learning=200

## Wave 7

Case | Result | Evidence
---|---|---
X1 | **PASS** | theme post 302 dash=200
X2 | **PASS** | locale ar rtl=yes
X3 | **PASS** | settings 200
X4 | **PASS** | skip target
X5 | **PASS** | logged-in catalog 200
X6 | **PASS** | john teach 200
X7 | **FAIL** | john users 200
X8 | **FAIL** | john superadmin 200
X9 | **FAIL** | mina finance 200
X10 | **PASS** | guest dash 302 loc=http://127.0.0.1:8000/login
X11 | **PASS** | foundation.demo 419

## Wave 0

Case | Result | Evidence
---|---|---
A0 | **PASS** | PHPUnit 15 tests 98 assertions OK
A1 | **PASS** | PHPUnit output did not print password in assertions

## Totals

74 PASS / 24 FAIL / 98 cases
