import{a2 as S,$ as e}from"./ui-BPUUO-e7.js";import{D as P}from"./react-datepicker-SmZNS67-.js";import{c as d}from"./utils-CmeZxMYV.js";import{B as E}from"./button-CN5-FRd-.js";import{P as N,b as $,a as B}from"./popover-C-xEPGgg.js";import{u as z}from"./useTranslation-Dp56GW0M.js";import{C as M}from"./calendar-BldfunvH.js";function R({value:a,onChange:c,placeholder:i,className:s,id:p,required:l,maxDate:k,minDate:u,showYearDropdown:h=!0,showMonthDropdown:g=!0,style:m,disabled:w=!1,filterDate:_}){const{t:f}=z(),[x,n]=S.useState(!1),v=r=>r?new Date(r):null,b=r=>{if(!r)return"";const o={year:"numeric",month:"short",day:"numeric"};return r.toLocaleDateString("en-US",o)},t=v(a),y=r=>{if(r){const o=r.getFullYear(),j=String(r.getMonth()+1).padStart(2,"0"),C=String(r.getDate()).padStart(2,"0"),D=`${o}-${j}-${C}`;c(D),n(!1)}else c("")};return e.jsxs("div",{className:d("w-full",s),children:[p&&e.jsx("input",{id:p,type:"hidden",value:a||"",required:l}),e.jsxs(N,{open:x,onOpenChange:n,children:[e.jsx($,{asChild:!0,children:e.jsxs(E,{variant:"outline",className:d("w-full justify-start text-left font-normal h-10",!a&&"text-muted-foreground"),style:m,disabled:w,children:[e.jsx(M,{className:"mr-2 h-4 w-4"}),a&&t?b(t):i||f("Select date")]})}),e.jsx(B,{className:"w-auto p-0",align:"start",children:e.jsx("div",{className:"date-picker-wrapper",children:e.jsx(P,{selected:t,onChange:y,inline:!0,showPopperArrow:!1,maxDate:k,minDate:u,showYearDropdown:h,showMonthDropdown:g,dropdownMode:"select",yearDropdownItemNumber:100,filterDate:_})})})]}),e.jsx("style",{children:`
        .date-picker-wrapper .react-datepicker {
          font-family: inherit;
          border: none;
          background: hsl(var(--background));
          color: hsl(var(--foreground));
        }
        .date-picker-wrapper .react-datepicker__header {
          background: hsl(var(--background));
          border-bottom: 1px solid hsl(var(--border));
          border-radius: 0;
        }
        .date-picker-wrapper .react-datepicker__current-month,
        .date-picker-wrapper .react-datepicker__day-name {
          color: hsl(var(--foreground));
          font-weight: 500;
        }
        .date-picker-wrapper .react-datepicker__day {
          color: hsl(var(--foreground));
          border-radius: 6px;
        }
        .date-picker-wrapper .react-datepicker__day:hover {
          background: hsl(var(--accent));
          color: hsl(var(--accent-foreground));
        }
        .date-picker-wrapper .react-datepicker__day--selected {
          background: hsl(var(--primary));
          color: hsl(var(--primary-foreground));
        }
        .date-picker-wrapper .react-datepicker__navigation {
          border: none;
          border-radius: 6px;
        }
        .date-picker-wrapper .react-datepicker__navigation:hover {
          background: hsl(var(--accent));
        }
        .date-picker-wrapper .react-datepicker__navigation-icon::before {
          border-color: hsl(var(--foreground));
        }
        .date-picker-wrapper .react-datepicker__day--outside-month {
          color: hsl(var(--muted-foreground));
        }
        .date-picker-wrapper .react-datepicker__day--disabled {
          color: hsl(var(--muted-foreground));
          opacity: 0.5;
        }
        .date-picker-wrapper .react-datepicker__month-container {
          background: hsl(var(--background));
        }
        .date-picker-wrapper .react-datepicker__header__dropdown {
          display: flex;
          gap: 8px;
          justify-content: center;
          padding: 8px 0;
        }
        .date-picker-wrapper .react-datepicker__month-dropdown-container,
        .date-picker-wrapper .react-datepicker__year-dropdown-container {
          margin: 0;
        }
        .date-picker-wrapper .react-datepicker__year-select,
        .date-picker-wrapper .react-datepicker__month-select {
          background: hsl(var(--background));
          color: hsl(var(--foreground));
          border: 1px solid hsl(var(--border));
          border-radius: 6px;
          padding: 6px 32px 6px 12px;
          font-size: 13px;
          font-weight: 500;
          cursor: pointer;
          outline: none;
          appearance: none;
          background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
          background-repeat: no-repeat;
          background-position: right 8px center;
          background-size: 12px;
          min-width: 80px;
        }
        .date-picker-wrapper .react-datepicker__month-select {
          min-width: 100px;
        }
        .date-picker-wrapper .react-datepicker__year-select:hover,
        .date-picker-wrapper .react-datepicker__month-select:hover {
          background-color: hsl(var(--accent));
          border-color: hsl(var(--border));
        }
        .date-picker-wrapper .react-datepicker__year-select:focus,
        .date-picker-wrapper .react-datepicker__month-select:focus {
          border-color: hsl(var(--ring));
          box-shadow: 0 0 0 2px hsl(var(--ring) / 0.2);
        }
      `})]})}export{R as D};
