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
   - ServiceNow will auto-generate the system **Name** (something like `u_st_pollination_ai_simple`)
3. Click **Submit**.
4. On the new table form, go to the **Columns** related list and add:
   - `u_prompt` (String, ~255) — the question you ask the AI
   - `u_response` (String, ~4000) — the answer the AI returns
   - Keep the existing `sys_id` (Sys ID / GUID)
5. (Optional but recommended) Create a module so it appears in the left-hand navigator:
   - On the table form, click **Create Module** or go to **System Definition → Modules → New**
   - Point it at your new table

**Remote Tables list** (your instance):
https://dev296154.service-now.com/now/nav/ui/classic/params/target/sys_db_object_list.do%3Fsysparm_userpref_module%3De9d76d1123403300edc0f4c947bf654e%26sysparm_view%3DTables%26sysparm_query%3Dscriptable_table%253Dtrue%255EEQ%26sysparm_clear_stack%3Dtrue

![Remote Tables list showing Pollination AI Simple](https://github.com/user-attachments/assets/PLACEHOLDER-remote-tables-list.png)

**Columns on the table:**
![Columns related list — sys_id, u_response, u_prompt](https://github.com/user-attachments/assets/PLACEHOLDER-columns.png)

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

![Definition form with IntegrationHub warning and Flow field](https://github.com/user-attachments/assets/PLACEHOLDER-definition-form.png)

### IntegrationHub entitlement note

The blue banner on the Definition form says:

> Creating a remote table that utilizes data from an external (outside ServiceNow) source requires an IntegrationHub entitlement and consumes IntegrationHub transactions.

This is official ServiceNow guidance. Using a remote table against an outside API (Pollinations, etc.) is treated as an Integration Hub use case.

- **Lab / PDI**: You can usually activate and use it for learning without a paid production subscription.
- **Production**: You need a real Integration Hub entitlement. Each external call can count against your transaction limit.

### What the Flow field does

The **Flow** field (Washington DC and later) lets you populate the remote table with a Flow Designer flow instead of a script.

**Order of operations when the remote table is opened:**
1. ServiceNow checks whether the Definition is Active.
2. If a **Flow** is filled in → it runs that flow (and the Script field is hidden).
3. If no Flow is set → it runs the **Query Script**.
4. The flow or script calls `v_table.addRow(...)` (or the Flow Designer “Create Remote Table Record” action).
5. The rows appear in the list/form (and can be cached if you set a TTL).

In Flow Designer the trigger is **Remote Table Query**. Inside the flow you call your REST/spoke action, then use **Create Remote Table Record** to add each row.

### Why `v_table` and `v_query` exist

- `v_table` — the object ServiceNow gives you so you can add rows.  
  `v_table.addRow(row)` is the only way to put a record into the remote table.
- `v_query` — represents the current request.  
  We mainly use `v_query.setLastErrorMessage(...)` so errors show up at the bottom of the list instead of failing silently.

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

**Resulting table list** (cooldown ~1 minute because of Pollinations rate limit):
https://dev296154.service-now.com/now/nav/ui/classic/params/target/u_st_pollination_ai_simple_list.do

> **Note:** The free Pollinations endpoint is rate-limited (about 1 request every 45 seconds per IP). If you see a 402 error, just wait a minute and refresh.

---

## Why Sys ID is not visible in the list (and how to show it)

By design, ServiceNow hides the `sys_id` field from the normal **Personalize List Columns** dialog (the gear icon). It is considered a technical/internal identifier, so it does not appear in the “Available” columns list for most tables, including custom and remote tables.

![Personalize List Columns — sys_id is missing from Available](https://github.com/user-attachments/assets/PLACEHOLDER-personalize-list-no-sysid.png)

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

## Deep dive: `v_table`, `v_query`, transforming data, cooldowns, and dynamic prompts

### Why do you add rows with `v_table`?

A remote table has **no database rows**. Every time someone opens the list, your script must manually build the rows and hand them to ServiceNow.  
`v_table.addRow(row)` is what makes a record appear.

**Example — returning 3 different AI answers:**
```javascript
var questions = ["What is 2 + 2?", "Capital of France?", "Color of the sky?"];

for (var i = 0; i < questions.length; i++) {
    var row = {};
    row.sys_id     = gs.generateGUID();
    row.u_prompt   = questions[i];
    row.u_response = callAI(questions[i]);   // your REST call
    v_table.addRow(row);                     // this is what makes the row appear
}
```
Without `v_table.addRow()`, nothing shows up in the list.

### What is `v_query` for?

`v_query` is the **request object**. It tells you what the user is asking for and lets you talk back to the platform.

Common uses:
- `v_query.setLastErrorMessage("something broke")` — shows a red error banner at the bottom of the list
- `v_query.isGet()` / `v_query.getSysId()` — someone opened a single form (not the list)
- `v_query.getParameter("u_prompt")` — the user filtered or searched on a field
- `v_query.getEncodedQuery()` — the full filter the user applied

You almost always use it for error reporting and for reading what the user is looking for.

### Transforming data (currency example)

You do the transformation **before** you call `v_table.addRow()`.  
Neither object does the math for you — you do it in normal JavaScript, then put the clean value in the row.

**Example — turn `"4.433"` into `$4.43`:**
```javascript
var raw = "4.433";                          // came from the API as a string
var number = parseFloat(raw);               // 4.433
var rounded = Math.round(number * 100) / 100; // 4.43
var currency = "$" + rounded.toFixed(2);    // "$4.43"

var row = {};
row.sys_id = gs.generateGUID();
row.u_amount = currency;                    // put the transformed value in the row
v_table.addRow(row);
```
You can do this for every field you want cleaned, formatted, rounded, etc.

### Safeguard against spamming the API (cooldown)

You can store the last call time in a system property (or a tiny local table) and skip the API if it is still in cooldown.

**Simple version using a system property:**
```javascript
var lastCall = gs.getProperty("pollinations.last_call", "0");
var now = new Date().getTime();
var cooldownMs = 45 * 1000;                 // 45 seconds

if (now - parseInt(lastCall) < cooldownMs) {
    v_query.setLastErrorMessage("Still in cooldown — wait " + 
        Math.ceil((cooldownMs - (now - parseInt(lastCall))) / 1000) + " seconds");
    return;
}

// ... do the API call ...

// after a successful call:
gs.setProperty("pollinations.last_call", now.toString());
```
This stops the script from hitting Pollinations again until the cooldown is over.

### Let the user supply their own prompt

Use `v_query` to read what the user typed in the list filter or a search field.

**Example — user types a prompt in the filter:**
```javascript
// If the user filtered on the prompt field, use that value
var userPrompt = v_query.getParameter("u_prompt");

// Fallback if they didn’t filter
if (!userPrompt) {
    userPrompt = "What is 2 + 2?";
}

// then use userPrompt in the API call instead of the hard-coded string
```
The user can type something in the **prompt** column filter (or you can add a UI Action / catalog item that sets the filter) and the remote table will call the AI with their text.

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
