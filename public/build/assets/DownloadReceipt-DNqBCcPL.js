import{a as v}from"./html2pdf-B88W1cO0.js";import{c as y,h as f,b as p}from"./helpers-dMT_p4Zy.js";import"./ui-BPUUO-e7.js";import"./app-P2ejYCRC.js";const A=async(i,a)=>{var r;const c=`
        <div class="receipt">
            <div class="header">
                <div class="company-name">${(a==null?void 0:a.company_name)||"COMPANY NAME"}</div>
                <div class="company-info">
                    ${(a==null?void 0:a.company_address)||"Company Address"}<br>
                    ${(a==null?void 0:a.company_city)||"City"}, ${(a==null?void 0:a.company_state)||"State"}<br>
                    ${(a==null?void 0:a.company_country)||"Country"} - ${(a==null?void 0:a.company_zipcode)||"Zipcode"}
                </div>
            </div>
            
            <div class="separator"></div>
            
            <div class="receipt-info">
                <div class="info-row">
                    <span>Receipt No:</span>
                    <span>${i.pos_number}</span>
                </div>
                <div class="info-row">
                    <span>Date:</span>
                    <span>${y(new Date,{companyAllSetting:a})}</span>
                </div>
                <div class="info-row">
                    <span>Time:</span>
                    <span>${f(new Date().toLocaleTimeString(),{companyAllSetting:a})}</span>
                </div>
                <div class="info-row">
                    <span>Customer:</span>
                    <span>${((r=i.customer)==null?void 0:r.name)||"Walk-in Customer"}</span>
                </div>
            </div>
            
            <div class="separator"></div>
            
            <div class="items-section">
                ${i.items.map(n=>{const d=n.price*n.quantity;let t=0,o="";return n.taxes&&n.taxes.length>0?o=n.taxes.map(e=>(t+=d*e.rate/100,`${e.name} (${e.rate}%)`)).join(", "):o="No Tax",`
                        <div class="item">
                            <div class="item-name">${n.name}</div>
                            <div class="item-details">
                                <div class="total-row">
                                    <span>Qty: ${n.quantity}</span>
                                    <span>Price: ${p(n.price,{companyAllSetting:a})}</span>
                                </div>
                                <div class="total-row">
                                    <span>Tax: ${o}</span>
                                    <span>Tax Amount: ${p(t,{companyAllSetting:a})}</span>
                                </div>
                                <div class="total-row" style="font-weight: bold;">
                                    <span>Subtotal:</span>
                                    <span>${p(d+t,{companyAllSetting:a})}</span>
                                </div>
                            </div>
                        </div>
                    `}).join("")}
            </div>
            
            <div class="separator"></div>
            
            <div class="totals">
                <div class="total-row">
                    <span>Discount:</span>
                    <span>-${p(i.discount,{companyAllSetting:a})}</span>
                </div>
                <div class="final-total">
                    <span>TOTAL:</span>
                    <span>${p(i.total,{companyAllSetting:a})}</span>
                </div>
            </div>
            
            <div class="separator"></div>
            
            <div class="footer">
                <div style="font-weight: bold;">*** THANK YOU ***</div>
                <div>Visit Again!</div>
            </div>
        </div>
        
        <style>
            .receipt { max-width: 400px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif; }
            .header { text-align: center; margin-bottom: 20px; }
            .company-name { font-size: 20px; font-weight: bold; margin-bottom: 10px; }
            .company-info { font-size: 12px; line-height: 1.4; }
            .separator { border-top: 1px dashed #000; margin: 15px 0; }
            .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
            .item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dotted #ccc; }
            .item-name { font-weight: bold; margin-bottom: 8px; }
            .item-details { font-size: 12px; }
            .total-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
            .final-total { display: flex; justify-content: space-between; font-weight: bold; font-size: 16px; border-top: 2px solid #000; padding-top: 10px; margin-top: 10px; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; }
        </style>
    `,s=document.createElement("div");s.innerHTML=c,document.body.appendChild(s);const m={margin:.1,filename:`receipt-${i.pos_number}.pdf`,image:{type:"jpeg",quality:.98},html2canvas:{scale:2},jsPDF:{unit:"mm",format:[80,297],orientation:"portrait"}};try{await v().set(m).from(s).save()}catch(n){console.error("PDF generation failed:",n)}finally{document.body.removeChild(s)}};export{A as downloadReceiptPDF};
