const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./html2pdf-B88W1cO0.js","./ui-BPUUO-e7.js","./app-P2ejYCRC.js","./app-C7O_fef7.css"])))=>i.map(i=>d[i]);
import{_ as m}from"./app-P2ejYCRC.js";import{$ as e}from"./ui-BPUUO-e7.js";import{B as c}from"./button-CN5-FRd-.js";import{T as f,c as b,a as g}from"./tooltip-DKc79Er6.js";import{b as x,c as n}from"./helpers-dMT_p4Zy.js";import{u as h}from"./useTranslation-BijID-On.js";import{D as v}from"./download-CrFZZ81H.js";import"./index-I7g51jjI.js";import"./index-BMyiKApA.js";import"./utils-B-dksMZM.js";import"./utils-CmeZxMYV.js";import"./index-BbpkoFKA.js";import"./createLucideIcon-BITY8JvQ.js";function F({template:t,variant:s="outline",size:a="sm"}){const{t:o}=h(),p=()=>{var r;const l=document.createElement("div");l.innerHTML=`
            <div style="padding: 40px; font-family: Arial, sans-serif;">
                <div style="border-bottom: 2px solid #e5e7eb; padding-bottom: 24px; margin-bottom: 32px; text-align: center;">
                    <h1 style="font-size: 24px; font-weight: bold; color: #111827; margin-bottom: 8px;">${t.subject}</h1>
                    <p style="color: #6b7280;">Template #${t.template_number}</p>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 32px;">
                    <div>
                        <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 16px;">${o("Template Information")}</h3>
                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${o("Subject")}</label>
                            <p style="font-weight: 500; color: #111827;">${t.subject}</p>
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${o("Template Number")}</label>
                            <p style="font-weight: 500; color: #111827;">${t.template_number}</p>
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${o("Contract Type")}</label>
                            <p style="font-weight: 500; color: #111827;">${((r=t.contract_type)==null?void 0:r.name)||"-"}</p>
                        </div>
                        <div>
                            <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${o("Status")}</label>
                            <p style="font-weight: 500; color: #111827;">${o(t.status.charAt(0).toUpperCase()+t.status.slice(1))}</p>
                        </div>
                    </div>
                    <div>
                        <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 16px;">${o("Template Details")}</h3>
                        ${t.user?`
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${o("Assigned To")}</label>
                                <p style="font-weight: 500; color: #111827;">${t.user.name}</p>
                            </div>
                        `:""}
                        ${t.value?`
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${o("Template Value")}</label>
                                <p style="font-size: 18px; font-weight: 600; color: #111827;">${x(t.value)}</p>
                            </div>
                        `:""}
                        ${t.start_date?`
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${o("Start Date")}</label>
                                <p style="font-weight: 500; color: #111827;">${n(t.start_date)}</p>
                            </div>
                        `:""}
                        ${t.end_date?`
                            <div>
                                <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${o("End Date")}</label>
                                <p style="font-weight: 500; color: #111827;">${n(t.end_date)}</p>
                            </div>
                        `:""}
                    </div>
                </div>
                ${t.description?`
                    <div style="margin-bottom: 32px;">
                        <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 16px;">${o("Description")}</h3>
                        <div style="border-left: 4px solid #d1d5db; padding-left: 16px;">
                            <div style="color: #374151; line-height: 1.6;">${t.description}</div>
                        </div>
                    </div>
                `:""}
                <div style="text-align: center; font-size: 12px; color: #9ca3af; padding-top: 32px; margin-top: 32px; border-top: 1px solid #e5e7eb;">
                    <p>${o("Generated on")} ${new Date().toLocaleDateString()}</p>
                </div>
            </div>
        `;const d={margin:.5,filename:`contract-template-${t.template_number}.pdf`,image:{type:"jpeg",quality:.98},html2canvas:{scale:2},jsPDF:{unit:"in",format:"letter",orientation:"portrait"}};m(()=>import("./html2pdf-B88W1cO0.js").then(i=>i.h),__vite__mapDeps([0,1,2,3]),import.meta.url).then(i=>{i.default().set(d).from(l).save()})};return e.jsxs(f,{children:[e.jsx(b,{asChild:!0,children:e.jsx(c,{variant:s,size:a,onClick:p,children:e.jsx(v,{className:"h-4 w-4"})})}),e.jsx(g,{children:e.jsx("p",{children:o("Download PDF")})})]})}export{F as default};
