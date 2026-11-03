# ServiceNow-Remote-Table


##1  Create the Remote Table
A remote table is a live view of external data (rows stay in memory — not stored in ServiceNow). Two pieces: the table schema, then a script that fills it.

1. Create the remote table
System Definition → Tables → New (or Remote Tables → Tables → New)
Label e.g. Toronto Events Remote
Check Remote Table (name becomes something like u_st_toronto_events_remote)
Add columns that match your MySQL fields (name, date, venue, etc.). sys_id is already there — leave it.
Optionally Create module so it shows in the nav → Submit


Quick Demo based on 
u_location	u_event_id	u_last_updated	u_event_date	u_title	sys_created_on	sys_created_by	sys_tags	sys_updated_on	sys_updated_by	sys_mod_count	sys_id
<img width="769" height="21" alt="image" src="https://github.com/user-attachments/assets/71930955-08a9-4a54-92cc-5afec00e9eea" />


Table config:
Columns

<img width="1434" height="690" alt="image" src="https://github.com/user-attachments/assets/399c9d00-ddee-44de-926e-51ebe2a333dc" />

Controls
<img width="1541" height="865" alt="image" src="https://github.com/user-attachments/assets/2c90fcee-a039-4063-ada3-1d670f4210b5" />

Application Access
<img width="1496" height="697" alt="image" src="https://github.com/user-attachments/assets/36ecc597-97f8-4b4b-914b-00a240d4bec6" />


Related Lists
<img width="1408" height="295" alt="image" src="https://github.com/user-attachments/assets/b5fe578a-d529-430f-9d6a-7e3c2fbb56c4" />
Related Lists-Labels:
<img width="1419" height="205" alt="image" src="https://github.com/user-attachments/assets/a98d639a-33c6-4957-8d27-bf46fc03d9a5" />
