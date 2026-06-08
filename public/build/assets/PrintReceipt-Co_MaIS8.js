import{c as x,h as y,b as o}from"./helpers-CMvbr1Vc.js";import"./app-DSMfeqOy.js";import"./ui-BPUUO-e7.js";/* empty css            */const l=(a,i)=>{var p,s,d,m;const f=`
    <!DOCTYPE html>
    <html>
    <head>
        <title>Receipt - ${a.pos_number}</title>
        <style>
            @page {
                size: 80mm auto;
                margin: 0;
            }
            @media print {
                body { 
                    width: 80mm;
                    margin: 0;
                    padding: 0;
                }
            }
            body { 
                font-family: 'Courier New', monospace; 
                width: 80mm;
                margin: 0; 
                padding: 0;
                font-size: 12px;
                line-height: 1.3;
                color: #000;
            }
            .receipt { 
                width: 100%;
                text-align: center;
                padding: 5mm;
                margin: 0;
                box-sizing: border-box;
            }
            .header {
                margin-bottom: 8px;
            }
            .company-name {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 3px;
                letter-spacing: 0.5px;
            }
            .company-info {
                font-size: 11px;
                line-height: 1.4;
                margin-bottom: 5px;
            }
            .separator {
                border-top: 2px dashed #000;
                margin: 8px 0;
            }
            .receipt-info {
                text-align: left;
                margin-bottom: 6px;
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 2px;
                font-size: 12px;
            }
            .items-section {
                text-align: left;
                margin-bottom: 6px;
            }
            .item {
                margin-bottom: 10px;
                border-bottom: 1px dotted #000;
                padding-bottom: 5px;
            }
            .item-name {
                font-weight: bold;
                font-size: 13px;
                margin-bottom: 3px;
            }
            .item-details {
                font-size: 11px;
            }
            .item-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 2px;
            }
            .totals {
                text-align: left;
                margin-bottom: 6px;
            }
            .total-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 3px;
                font-size: 12px;
            }
            .final-total {
                display: flex;
                justify-content: space-between;
                font-weight: bold;
                font-size: 16px;
                border-top: 2px solid #000;
                padding-top: 5px;
                margin-top: 5px;
            }
            .footer {
                text-align: center;
                margin-top: 10px;
                font-size: 11px;
            }
            .thank-you {
                font-weight: bold;
                margin-bottom: 3px;
            }
        </style>
    </head>
    <body>
        <div class="receipt">
            <div class="header">
                <div class="company-name">${(i==null?void 0:i.company_name)||"COMPANY NAME"}</div>
                <div class="company-info">
                    ${(i==null?void 0:i.company_address)||"Company Address"}<br>
                    ${(i==null?void 0:i.company_city)||"City"}, ${(i==null?void 0:i.company_state)||"State"}<br>
                    ${(i==null?void 0:i.company_country)||"Country"} - ${(i==null?void 0:i.company_zipcode)||"Zipcode"}
                </div>
            </div>
            
            <div class="separator"></div>
            
            <div class="receipt-info">
                <div class="info-row">
                    <span>Receipt No:</span>
                    <span>${a.pos_number}</span>
                </div>
                <div class="info-row">
                    <span>Date:</span>
                    <span>${x(new Date,{companyAllSetting:i})}</span>
                </div>
                <div class="info-row">
                    <span>Time:</span>
                    <span>${y(new Date().toLocaleTimeString(),{companyAllSetting:i})}</span>
                </div>
                <div class="info-row">
                    <span>Customer:</span>
                    <span>${((p=a.customer)==null?void 0:p.name)||"Walk-in Customer"}</span>
                </div>
            </div>
            
            <div class="separator"></div>
            
            <div class="items-section">
                ${a.items.map(n=>{const r=n.price*n.quantity,c=n.taxes&&n.taxes.length>0?n.taxes[0].rate:0,v=r*c/100;return`
                        <div class="item">
                            <div class="item-name">${n.name}</div>
                            <div class="item-details">
                                <div class="item-row">
                                    <span>Qty:</span>
                                    <span>${n.quantity}</span>
                                </div>
                                <div class="item-row">
                                    <span>Price:</span>
                                    <span>${o(n.price,{companyAllSetting:i})}</span>
                                </div>
                                <div class="item-row">
                                    <span>Tax (${c}%):</span>
                                    <span>${o(v,{companyAllSetting:i})}</span>
                                </div>
                                <div class="item-row" style="font-weight: bold;">
                                    <span>Subtotal:</span>
                                    <span>${o(r+v,{companyAllSetting:i})}</span>
                                </div>
                            </div>
                        </div>
                    `}).join("")}
            </div>
            
            <div class="separator"></div>
            
            <div class="totals">
                <div class="total-row">
                    <span>Discount:</span>
                    <span>-${o(a.discount,{companyAllSetting:i})}</span>
                </div>
                <div class="final-total">
                    <span>TOTAL:</span>
                    <span>${o(a.total,{companyAllSetting:i})}</span>
                </div>
            </div>
            
            <div class="separator"></div>
            
            <div class="footer">
                <div class="thank-you">*** THANK YOU ***</div>
                <div>Visit Again!</div>
            </div>
        </div>
    </body>
    </html>
    `,t=document.createElement("iframe");t.style.display="none",document.body.appendChild(t);const e=t.contentDocument||((s=t.contentWindow)==null?void 0:s.document);e&&(e.write(f),e.close(),(d=t.contentWindow)==null||d.focus(),(m=t.contentWindow)==null||m.print(),setTimeout(()=>{document.body.removeChild(t)},1e3))};export{l as printReceipt};
