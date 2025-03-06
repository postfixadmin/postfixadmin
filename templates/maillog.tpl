
<div>
    <button class="btn" style="margin-bottom: 25px;"  onclick="changeTable('auth_logs')">   <span class="glyphicon glyphicon glyphicon-log-in">     </span> AUTH</button>
    <button class="btn" style="margin-bottom: 25px;"  onclick="changeTable('dovecot_logs')"><span class="glyphicon glyphicon glyphicon-align-left"> </span> IMAP/POP3</button>
    <button class="btn" style="margin-bottom: 25px;"  onclick="changeTable('postfix_logs')"><span class="glyphicon glyphicon glyphicon-align-right"></span> SMTP</button>
    
    <table class="table table-bordered table-striped">     
        <thead id="table_header">           
        </thead>
        <tbody id="content_logs">             
        </tbody>
    </table> 
    
    <div id="error_msg" style="margin-bottom: 50px;"></div>  
    <div id="pages" style="margin-bottom: 50px;"></div>
</div>



<script>
    let CURRENT_PAGE = 1;
    function httpGET(url){
        var xmlHttp = new XMLHttpRequest();
        console.log(url);
        xmlHttp.open( "GET", url, false ); 
        xmlHttp.send( null );
        return xmlHttp.responseText;
    }

    function setLogs(logs, table){
        
        document.getElementById("content_logs").innerHTML="";
        document.getElementById("error_msg").innerHTML="";
        try {
            logs = JSON.parse(logs);
        } catch (error) {
            document.getElementById("error_msg").innerHTML=`
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">Error to load data</h4>
                    <hr>
                    <p class="mb-0">
                        `+logs+`
                    </p>
                </div>     
            `;
            
            return;
        }
       
        if(logs["data"].length == 0 ){
            document.getElementById("content_logs").innerHTML="No records found!";
            return;
        }

        for (let i=0; i<logs["data"].length; i++){
            if (table == "auth_logs"){    
                document.getElementById("content_logs").innerHTML+=`            
                    <tr>
                        <td style="width:5%">   `+logs["data"][i]["month"]  +`</td>
                        <td style="width:5%">   `+logs["data"][i]["day"]    +`</td>
                        <td style="width:5%">   `+logs["data"][i]["hour"]   +`</td>
                        <td style="width:20%">  `+logs["data"][i]["domain"] +`</td>
                        <td style="width:20%">  `+logs["data"][i]["ip"]     +`</td>
                        <td style="width:20%">  `+logs["data"][i]["email"]  +`</td>
                        <td style="width:20%">  `+logs["data"][i]["log"]    +`</td>
                    </tr>         
                `;
                continue;
            }
            if (table == "dovecot_logs"){    
                document.getElementById("content_logs").innerHTML+=`
                    <tr>
                        <td style="width:5%">`  +logs["data"][i]["month"]   +`</td>
                        <td style="width:5%">`  +logs["data"][i]["day"]     +`</td>
                        <td style="width:5%">`  +logs["data"][i]["hour"]    +`</td>
                        <td style="width:20%">` +logs["data"][i]["domain"]  +`</td>
                        <td style="width:20%">` +logs["data"][i]["email"]   +`</td>
                        <td style="width:20%">` +logs["data"][i]["msgid"]   +`</td>
                        <td style="width:20%">` +logs["data"][i]["log"]     +`</td>
                    </tr>
                `;
                continue;
            }
            if (table == "postfix_logs"){    
                document.getElementById("content_logs").innerHTML+=`
                    <tr>
                        <td style="width:5%">`  +logs["data"][i]["month"]               +`</td>
                        <td style="width:5%">`  +logs["data"][i]["day"]                 +`</td>
                        <td style="width:5%">`  +logs["data"][i]["hour"]                +`</td>
                        <td style="width:10%">` +logs["data"][i]["mail_from"]           +`</td>
                        <td style="width:10%">` +logs["data"][i]["mail_from_domain"]    +`</td>
                        <td style="width:10%">` +logs["data"][i]["mail_to"]             +`</td>
                        <td style="width:10%">` +logs["data"][i]["mail_to_domain"]      +`</td>
                        <td style="width:10%">` +logs["data"][i]["status"]              +`</td>
                        <td style="width:10%">` +logs["data"][i]["msgid"]               +`</td>
                    </tr>
                `;
                continue;
            }
        }
        
        let htmlPages=""
        for (let i=1; i<=logs["number of pages"]; i++){
            htmlPages+=`
                <li><a onclick=onBtnPage("`+table+`","`+i+`") href="#">`+i+`</a></li>
            `;
        }

        document.getElementById("pages").innerHTML=`
        <div class="btn-group " role="group"><div class="dropdown">
			<button class="btn dropdown-toggle" type="button" data-toggle="dropdown">Page `+CURRENT_PAGE+ `<span class="caret"></span></button>
			    <ul class="dropdown-menu" style="max-height: 200px; overflow: auto;">
                        `+htmlPages+`
                </ul>
		</div>
        `;
    }

    /**
     * 
     * on page button is pressed
     * 
     * **/
    function onBtnPage(table, pageSelected){
        CURRENT_PAGE=pageSelected;
        doSearch(table);
    }

    /**
     * 
     * on search button is pressed
     * 
     * **/
    function onBtnSearch(table){
        CURRENT_PAGE=1;
        doSearch(table);
    }
    
    function doSearch(table){
        let parameters = getParameters(table);

        //update page
        setLogs(httpGET(getWindowLocation()+parameters), table);
    }

    /**
     * 
     * get values from active table header
     * 
     * **/
    function getParameters(table){
        //get input id's
        let i=1;
        let ids=[];
        let elements = document.getElementById("table_header").innerHTML.split("id=\"")
     
        while(i<elements.length){
            ids[i-1]=elements[i].split("\"")[0];
            i++;
        }
        console.log(ids);
        //get input
        let parameters="?table="+table;
        for (let i=0; i<ids.length; i++){
            let value = document.getElementById(ids[i]).value;
            if( value == "" ) continue

            parameters+="&";
            parameters+=ids[i].split(table.split("_")[0]+"_")[1];
            parameters+="="+value;
        }
        parameters+="&page="+CURRENT_PAGE;
        console.log(getWindowLocation()+parameters);
        return parameters;
    }

    function getWindowLocation(){
        return String(window.location).split("#")[0];
    }

    /**
     * 
     * update current table body
     * 
     * **/
    function changeTable(table){
        console.log(table);
        if (table == "auth_logs"){
            document.getElementById("table_header").innerHTML = `
                <tr>
                    <th style="width:5%">
                        <label for="auth_month">month</label>
                        <input class="form-control input-sm" id="auth_month" type="text">
                    </th>
                    <th style="width:5%">
                        <label for="auth_day">day</label>
                        <input class="form-control input-sm" id="auth_day" type="text">
                    </th>
                    <th style="width:5%" >
                        <label for="auth_hour">hour</label>
                        <input class="form-control input-sm" id="auth_hour" type="text">
                    </th>
                    <th style="width:20%">
                        <label for="auth_domain">domain</label>
                        <input class="form-control input-sm" id="auth_domain" type="text">
                    </th>
                    <th style="width:20%">
                        <label for="auth_ip">ip</label>
                        <input class="form-control input-sm" id="auth_ip" type="text">
                    </th>
                    <th style="width:20%">
                        <label for="auth_email">email</label>
                        <input class="form-control input-sm" id="auth_email" type="text">
                    </th>
                    <th style="width:20%">
                        <label for="auth_log">log</label>
                        <input class="form-control input-sm" id="auth_log" type="text">
                    </th>
                    <th style="width:5%">
                        <button class="btn" style="margin-top: 25px;"  onclick="onBtnSearch('auth_logs')"><span class="glyphicon glyphicon-search"></span> search</button>
                    </th>
                </tr>
                `;
            //set first page
            CURRENT_PAGE=1;
            setLogs(httpGET(getWindowLocation()+"?table=auth_logs"), "auth_logs");
            return 0;
        }
        if (table == "dovecot_logs"){
            document.getElementById("table_header").innerHTML = `
                <tr>
                  <th style="width:5%">
                      <label for="dovecot_month">month</label>
                      <input class="form-control input-sm" id="dovecot_month" type="text">
                  </th>
                  <th style="width:5%">
                      <label for="dovecot_day">day</label>
                      <input class="form-control input-sm" id="dovecot_day" type="text">
                  </th>
                  <th style="width:5%" >
                      <label for="dovecot_hour">hour</label>
                      <input class="form-control input-sm" id="dovecot_hour" type="text">
                  </th>
                  <th style="width:20%">
                      <label for="dovecot_domain">domain</label>
                      <input class="form-control input-sm" id="dovecot_domain" type="text">
                  </th>
                  <th style="width:20%">
                      <label for="dovecot_email">email</label>
                      <input class="form-control input-sm" id="dovecot_email" type="text">
                  </th>
                  <th style="width:20%">
                      <label for="dovecot_msgid">msgid</label>
                      <input class="form-control input-sm" id="dovecot_msgid" type="text">
                  </th>
                  <th style="width:20%">
                      <label for="dovecot_log">log</label>
                      <input class="form-control input-sm" id="dovecot_log" type="text">
                  </th>
                  <th style="width:5%">
                      <button class="btn" style="margin-top: 25px;"  onclick="onBtnSearch('dovecot_logs')"><span class="glyphicon glyphicon-search"></span> search</button>
                  </th>
                </tr>
            `;
            CURRENT_PAGE=1;
            //set first page
            setLogs(httpGET(getWindowLocation()+"?table=dovecot_logs"), "dovecot_logs");
            return 0;
        }

        if (table == "postfix_logs"){
            document.getElementById("table_header").innerHTML = `
                 <tr>
                  <th style="width:5%">
                      <label for="postfix_month">month</label>
                      <input class="form-control input-sm" id="postfix_month" type="text">
                  </th>
                  <th style="width:5%">
                      <label for="postfix_day">day</label>
                      <input class="form-control input-sm" id="postfix_day" type="text">
                  </th>
                  <th style="width:5%" >
                      <label for="postfix_hour">hour</label>
                      <input class="form-control input-sm" id="postfix_hour" type="text">
                  </th>
                  <th style="width:10%">
                    <label for="postfix_mail_from">email from</label>
                    <input class="form-control input-sm" id="postfix_mail_from" type="text">
                    </th>
                    <th style="width:10%">
                        <label for="postfix_mail_from_domain">email from domain</label>
                        <input class="form-control input-sm" id="postfix_mail_from_domain" type="text">
                    </th>
                  <th style="width:10%">
                      <label for="postfix_mail_to">email to</label>
                      <input class="form-control input-sm" id="postfix_mail_to" type="text">
                  </th>
                  <th style="width:10%">
                      <label for="postfix_mail_to_domain">email to domain</label>
                      <input class="form-control input-sm" id="postfix_mail_to_domain" type="text">
                  </th>
                    <th style="width:10%">
                        <label for="postfix_status">status</label>
                        <input class="form-control input-sm" id="postfix_status" type="text">
                    </th>
                  <th style="width:10%">
                      <label for="postfix_msgid">msgid</label>
                      <input class="form-control input-sm" id="postfix_msgid" type="text">
                  </th>
                  
                  <th style="width:5%">
                      <button class="btn" style="margin-top: 25px;"  onclick="onBtnSearch('postfix_logs')"><span class="glyphicon glyphicon-search"></span> search</button>
                  </th>
                </tr>
            `;
            CURRENT_PAGE=1;
            //set first page
            setLogs(httpGET(getWindowLocation()+"?table=postfix_logs"), "postfix_logs");
            return 0;
        }

    }
    //show first table
    changeTable('auth_logs');
    
</script>
