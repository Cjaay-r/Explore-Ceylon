<?php
function country_code_field($nameCountry='Phone_Country',$nameLocal='Phone_Local',$prefillDial='',$prefillLocal=''){
  $id='ccp_'.bin2hex(random_bytes(3));
  $prefillDial=htmlspecialchars($prefillDial??'',ENT_QUOTES);
  $prefillLocal=htmlspecialchars($prefillLocal??'',ENT_QUOTES);
  echo '
  <div class="phone-row" id="'.$id.'">
    <div class="cc-wrap" style="min-width:260px">
      <div class="cc-search">
        <input type="text" class="form-control" id="'.$id.'_q" placeholder="Type country or code">
        <button type="button" class="cc-clear btn btn-outline-secondary" id="'.$id.'_clear" style="margin-left:6px">×</button>
      </div>
      <select class="form-select mt-1" id="'.$id.'_country" name="'.$nameCountry.'" data-prefill="'.$prefillDial.'">
        <option value="">Loading…</option>
      </select>
    </div>
    <input type="text" name="'.$nameLocal.'" id="'.$id.'_local" class="form-control" required value="'.$prefillLocal.'" placeholder="Enter phone number" style="margin-left:10px">
  </div>
  <script>
  (function(){
    const src="Includes/CountryCodes.json";
    const root=document.getElementById("'.$id.'");
    const sel=root.querySelector("#'.$id.'_country");
    const q=root.querySelector("#'.$id.'_q");
    const clearBtn=root.querySelector("#'.$id.'_clear");
    let data=[],filtered=[];
    function fmt(c){return c.name+" ("+c.dial_code+")"}
    function render(list){
      const v=sel.value;
      sel.innerHTML="";
      list.forEach(c=>{
        const o=document.createElement("option");
        o.value=c.dial_code; o.textContent=fmt(c); o.setAttribute("data-iso",c.code);
        sel.appendChild(o);
      });
      if(list.some(c=>c.dial_code===v)) sel.value=v; else if(list.length) sel.selectedIndex=0;
      sel.dispatchEvent(new Event("change"));
    }
    function normalize(s){return s.toLowerCase().normalize("NFD").replace(/\\p{Diacritic}/gu,"")}
    function applyFilter(){
      const n=normalize(q.value);
      filtered=data.filter(c=>normalize(c.name).includes(n)||c.code.toLowerCase().includes(n)||c.dial_code.replace("+","").includes(n.replace("+","")));
      render(filtered);
    }
    function loadPrefill(){
      const pre=(sel.getAttribute("data-prefill")||"").trim();
      if(!pre) return;
      const byDial=data.find(c=>c.dial_code===pre);
      const byIso=data.find(c=>c.code===pre.toUpperCase());
      const byText=data.find(c=>fmt(c)===pre||c.name===pre);
      const pick=byDial||byIso||byText;
      if(pick) sel.value=pick.dial_code;
    }
    clearBtn.addEventListener("click",()=>{q.value="";applyFilter();q.focus()});
    q.addEventListener("input",applyFilter);
    fetch(src).then(r=>r.json()).then(j=>{
      data=j.map(x=>({name:x.name,code:x.code,dial_code:x.dial_code})).sort((a,b)=>a.name.localeCompare(b.name));
      filtered=[...data];
      render(filtered);
      loadPrefill();
    });
  })();
  </script>';
}
