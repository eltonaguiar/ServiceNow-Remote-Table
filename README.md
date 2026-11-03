# ServiceNow Remote Table — Beginner Guide (Pollinations AI example)

A remote table lets you show live data from an outside source (like an AI API) inside ServiceNow without storing the rows in the database. The table definition lives in ServiceNow; the rows are built in memory every time someone opens the list.

This guide walks through a simple example that calls the free Pollinations AI endpoint.

---

## Step-by-step: Create the Remote Table

**Navigation breadcrumbs:**  
Application Navigator → **System Definition** → **Remote Tables** → **Tables** → **New**

1. Click **New**.
2. Fill in:
   - **Label**: `Pollination AI Simple` (this is the friendly name users see)
   - Make sure the **Remote Table** checkbox is checked
   - ServiceNow will auto-generate the system **Name** (something like `u_pollination_ai_simple`)
3. Click **Submit**.
4. On the new table form, go to the **Columns** related list and add:
   - `u_prompt` (String, ~255) — the question you ask the AI
   - `u_response` (String, ~4000) — the answer the AI returns
   - Keep the existing `sys_id` (Sys ID / GUID)
5. (Optional but recommended) Create a module so it appears in the left-hand navigator:
   - On the table form, click **Create Module** or go to **System Definition → Modules → New**
   - Point it at your new table

After you save, you should see something like this in the navigator:

![Navigator showing Pollination AI Simple module](https://github.com/user-attachments/assets/PLACEHOLDER-screenshot-pollination-modules.png)

*(Upload the screenshot you shared of the navigator modules and replace the URL above.)*

---

## Step-by-step: Create the Query Script (Definition)

**Navigation breadcrumbs:**  
Application Navigator → **System Definition** → **Remote Tables** → **Definitions** → **New**

1. Click **New**.
2. Fill in:
   - **Name**: `Pollinations Query`
   - **Table**: select your remote table (`Pollination AI Simple`)
   - **Active**: checked
3. Scroll to the **Query** script field, delete the default template, and paste the code below.
4. **Save**.

### Why `v_table` and `v_query` exist

- `v_table` — the object ServiceNow gives you so you can add rows.  
  `v_table.addRow(row)` is the only way to put a record into the remote table.
- `v_query` — represents the current request.  
  We mainly use `v_query.setLastErrorMessage(...)` so errors show up at the bottom of the list instead of failing silently.

We don’t use the advanced filtering methods because this demo only returns one hard-coded row.

```javascript
// This function runs every time someone opens the remote table list
(function executeQuery(v_table, v_query) {

    try {
        // 1. The question we want to ask the AI
        var prompt = "What is 2 + 2?";

        // 2. Make the prompt safe for a URL
        // encodeURIComponent turns spaces and special characters into a safe format
        // Example: "What is 2 + 2?" becomes "What%20is%202%20%2B%202%3F"
        var safePrompt = encodeURIComponent(prompt);

        // Full URL example:
        // https://text.pollinations.ai/What%20is%202%20%2B%202%3F?model=openai-fast
        var endpoint = "https://text.pollinations.ai/" + safePrompt + "?model=openai-fast";

        // 3. Create a REST message (ServiceNow’s tool for calling external websites)
        var rm = new sn_ws.RESTMessageV2();
        rm.setEndpoint(endpoint);
        rm.setHttpMethod("GET");   // GET = just ask for data, no body needed

        // 4. Send the request
        var response = rm.execute();
        var status   = response.getStatusCode();  // 200 = success
        var body     = response.getBody();        // the plain text answer

        // 5. Handle errors
        if (response.haveError() || status != 200) {
            v_query.setLastErrorMessage("Pollinations error " + status + ": " + body);
            return;
        }

        // 6. Build one row and add it
        var row = {};
        row.sys_id     = gs.generateGUID();  // unique ID so the form can open
        row.u_prompt   = prompt;
        row.u_response = body;

        v_table.addRow(row);

    } catch (ex) {
        v_query.setLastErrorMessage(ex.message);
    }

})(v_table, v_query);
```

**To test:** Open the list of your remote table from the navigator. The script runs automatically and you should see one row.

> **Note:** The free Pollinations endpoint is rate-limited (about 1 request every 45 seconds per IP). If you see a 402 error, just wait a minute and refresh.

---

## Why Sys ID is not visible in the list (and how to show it)

By design, ServiceNow hides the `sys_id` field from the normal **Personalize List Columns** dialog (the gear icon). It is considered a technical/internal identifier, so it does not appear in the “Available” columns list for most tables, including custom and remote tables.

It still exists on every row (your script correctly sets `row.sys_id = gs.generateGUID()`), you just can’t add it through the normal personalize UI.

### How to make Sys ID visible on the list

**Navigation breadcrumbs:**  
Application Navigator → **System UI** → **Lists**

1. Find the list record for your table (`u_st_pollination_ai_simple`) and the view you use (usually “Default”).
2. Open that list record.
3. In the **List Elements** related list, click **New**.
4. Set:
   - **Element** = `sys_id`
   - **Position** = whatever order you want (e.g. 100 or 1)
5. Save.
6. Go to `cache.do` in the browser (or log out and back in) to clear the cache.
7. Re-open your remote table list.  
   Sys ID should now appear as a column. You can then personalize/reorder it if needed.

**Alternative (form view):**  
Right-click the form header → **Configure** → **Form Layout** → double-click in the empty space outside the slushbucket → `sys_id` appears → move it to the selected side.

---

## Access Control — Who can see the remote table?

Remote tables follow the same security rules as normal tables.

### What you did
You created a role (or user) called `u_pollination_ai_simple_user` and (presumably) granted that role access to the table.

### What happens if a user **without** that role tries to open the table

- They will **not** see the module in the navigator (if the module is restricted).
- If they somehow navigate to the list URL directly, ServiceNow will show a **security constraint** / access denied message.
- They will not see any rows, and the query script will not even run for them.

### How to control it

**Navigation breadcrumbs:**  
Application Navigator → **System Definition** → **Remote Tables** → **Tables** → open your table → **Application Access** tab  
or  
**System Security** → **Access Control (ACL)** → search for your table name

- On the table form under **Application Access**, you can restrict which applications/roles can access it.
- For finer control, create ACLs on the table (read, write, etc.) that require the `u_pollination_ai_simple_user` role.
- Modules can also have a **Roles** field so only users with that role see the link in the navigator.

**Result:** Only users who have the `u_pollination_ai_simple_user` role (or admin) can open the list and see the AI responses.

---

## Beyond this demo — things you can do next

- Change the hard-coded `prompt` to read from a system property, a query parameter, or user input.
- Call the OpenAI-compatible endpoint (`https://text.pollinations.ai/openai`) so you get JSON + reasoning.
- Add a free API key from [enter.pollinations.ai](https://enter.pollinations.ai) to remove the rate limit.
- Cache the results (set a TTL on the Definition) so it doesn’t call the AI on every page load.
- Make the table editable and handle insert/update/delete scripts if you want to write back to the external system.
- Use the same pattern to pull from your own MySQL table (expose it as a REST API first).

---

<details>
<summary><strong>IGNORE BELOW — future updates / extra screenshots</strong></summary>

Quick Demo based on 
u_location	u_event_id	u_last_updated	u_event_date	u_title	sys_created_on	sys_created_by	sys_tags	sys_updated_on	sys_updated_by	sys_mod_count	sys_id
<img width="769" height="21" alt="image" src="https://github.com/user-attachments/assets/71930955-08a9-4a54-92cc-5afec00e9eea" />

Table config: Columns
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

---

# Remote Table out-of-box sample code (reference only)

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
     //      retrieve-rows-from-external-system;
     //      if (there-was-an-error) {
     //          var message = ...;
     //          v_query.setLastErrorMessage(message);
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
