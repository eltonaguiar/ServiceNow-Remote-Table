# ServiceNow-Remote-Table

## 1 Create the Remote Table
A remote table is a live view of external data (rows stay in memory — not stored in ServiceNow). Two pieces: the table schema, then a script that fills it.

1. Create the remote table
System Definition → Tables → New (or Remote Tables → Tables → New)
Label e.g. Toronto Events Remote
Check Remote Table (name becomes something like u_st_toronto_events_remote)
Add columns that match your MySQL fields (name, date, venue, etc.). sys_id is already there — leave it.
Optionally Create module so it shows in the nav → Submit

# Bare Minimum use Pollinations API
i.e. something like this
<img width="720" height="70" alt="image" src="https://github.com/user-attachments/assets/b383ab6a-78e7-492c-90d3-e67211710aa6" />
Can also visit it in a browser and it will render 2+2=4 (where 4 came from the AI )
https://text.pollinations.ai/What%20is%202+2?model=openai-fast
<img width="1429" height="661" alt="image" src="https://github.com/user-attachments/assets/69bc7a51-951e-47cf-945a-d008eb630600" />

<img width="199" height="85" alt="image" src="https://github.com/user-attachments/assets/0b37bda5-5475-435c-b7fc-d489c006a420" />

# Remote Table Actual Code

**Why we use `v_table` and `v_query`**

- `v_table` — the special object ServiceNow gives you so you can add rows to the remote table.
  `v_table.addRow(row)` is the only way to put a record into the table.
- `v_query` — the object that represents the current request.
  We use it mainly for `v_query.setLastErrorMessage(...)` so errors appear at the bottom of the list instead of failing silently.

That’s all we need from them. We don’t use the more advanced query-filtering methods because we’re only returning one hard-coded row.

```javascript
// This is the main function ServiceNow runs every time someone opens your remote table
(function executeQuery(v_table, v_query) {

    try {
        // -------------------------------------------------
        // 1. The question we want to ask the AI
        // -------------------------------------------------
        var prompt = "What is 2 + 2?";
        // Later you can change this to whatever you want, or even make it dynamic

        // -------------------------------------------------
        // 2. Build the URL that Pollinations understands
        // -------------------------------------------------
        // encodeURIComponent turns spaces and special characters into a safe URL format
        // Example: "What is 2 + 2?" becomes "What%20is%202%20%2B%202%3F"
        var safePrompt = encodeURIComponent(prompt);

        // The full URL looks like:
        // https://text.pollinations.ai/What%20is%202%20%2B%202%3F?model=openai-fast
        var endpoint = "https://text.pollinations.ai/" + safePrompt + "?model=openai-fast";

        // -------------------------------------------------
        // 3. Create a REST message (this is how ServiceNow calls websites/APIs)
        // -------------------------------------------------
        var rm = new sn_ws.RESTMessageV2();
        rm.setEndpoint(endpoint);          // the URL we built above
        rm.setHttpMethod("GET");           // GET = just ask for data (no body needed)

        // -------------------------------------------------
        // 4. Send the request and get the answer
        // -------------------------------------------------
        var response = rm.execute();       // actually call Pollinations
        var status   = response.getStatusCode();  // 200 means success
        var body     = response.getBody();        // the plain text answer from the AI

        // -------------------------------------------------
        // 5. Check if something went wrong
        // -------------------------------------------------
        if (response.haveError() || status != 200) {
            // Show the error at the bottom of the list view
            v_query.setLastErrorMessage("Pollinations error " + status + ": " + body);
            return; // stop here
        }

        // -------------------------------------------------
        // 6. Create one row and put it in the remote table
        // -------------------------------------------------
        var row = {};

        // Every row needs a unique sys_id so ServiceNow can open the form
        row.sys_id = gs.generateGUID();

        // These names MUST match the Column name fields you created earlier
        row.u_prompt   = prompt;   // the question we asked
        row.u_response = body;     // the answer Pollinations gave us

        // Finally add the row to the table
        v_table.addRow(row);

    } catch (ex) {
        // If anything unexpected breaks, show the error message
        v_query.setLastErrorMessage(ex.message);
    }

})(v_table, v_query);
```

# Advanced Options FYI
****<img width="1433" height="658" alt="image" src="https://github.com/user-attachments/assets/23e68340-dd0d-4181-b825-00105cb43742" />

Result:
<img width="1841" height="178" alt="image" src="https://github.com/user-attachments/assets/b5e97ece-ea0f-41c6-b29a-8d87fca051e8" />

<details>
<summary><strong>IGNORE BELOW , this would be covered in a future update</strong></summary>

=-===================================

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

</details>

# Remote Table out of box sample code

```javascript
(function executeQuery(v_table, v_query) {
     // Parameters:
     //      v_table - object to record response:
     //          v_table.addRow({ ... })                - adds a row to the result set.
     //              keys are the column names of the table definition.
     //              sys_id must be non-empty and uniquely identify each row.
     //                  the above rule will be strictly enforced for editable tables, and generate warnings for read-only tables.
     //                  sys_id will be supplied to the Update, and Delete scripts to specify the row's identity.
 
     //      v_query - the query definition:
     //          v_query.getEncodedQuery()              - encoded querystring
     //          v_query.getCondition(field)            - encoded querystring for the given field (includes field name, operator, and value)
     //          v_query.getParameter(field)            - parameter for the given field (only includes value for equality conditions)
     //          v_query.isGet()                        - whether the query is a single get by sys_id
     //          v_query.getSysId()                     - parameter for sys_id field
     //          v_query.isTextSearch()                 - whether the query contains a text query parameter
     //          v_query.getTextSearch()                - text search query parameter (internal field name 123TEXTQUERY321)
     //          v_query.getFirstRowWanted()            - the first row to include, don't use with caching
     //          v_query.getLastRowWanted()             - the last row to include, don't use with caching
     //          v_query.setLastErrorMessage(message)   - the message is visible from GlideRecord.getLastErrorMessage()
 
     // Sample code:
     //  try {
     //      retrieve-rows-from-external-system;     //      if (there-was-an-error) {
     //          var message = ...;     //          v_query.setLastErrorMessage(message);
     //          return;
     //      }
     //
     //      for (var row in rows) {
     //          v_table.addRow(row);
     //      }
     //  } catch (ex) {
     //      var message = ex.getMessage();
     //      v_query.setLastErrorMessage(message);
     //  }
 
  })(v_table, v_query);
```
