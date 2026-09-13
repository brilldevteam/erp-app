import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { SearchInput } from '@/components/ui/search-input';
import { ListGridToggle } from '@/components/ui/list-grid-toggle';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { FilterButton } from '@/components/ui/filter-button';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import NoRecordsFound from '@/components/no-records-found';
import { Plus, Eye, ShoppingCart } from 'lucide-react';

const classes: Record<string,string>={draft:'bg-gray-100 text-gray-700',pending_approval:'bg-amber-100 text-amber-700',approved:'bg-emerald-100 text-emerald-700',issued:'bg-blue-100 text-blue-700',rejected:'bg-red-100 text-red-700',cancelled:'bg-red-100 text-red-700',closed:'bg-slate-100 text-slate-700',unbilled:'bg-gray-100 text-gray-700',partially_billed:'bg-amber-100 text-amber-700',billed:'bg-emerald-100 text-emerald-700'};
const label=(value:string)=>value.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());

export default function Index({orders,vendors,warehouses,filters:initial}:any){
 const {t}=useTranslation(); const {auth}:any=usePage().props; const url=new URLSearchParams(location.search);
 const [filters,setFilters]=useState({search:initial.search||'',vendor_id:initial.vendor_id||'',warehouse_id:initial.warehouse_id||'',order_status:initial.order_status||'',billing_status:initial.billing_status||'',date_range:initial.date_range||''});
 const [viewMode,setViewMode]=useState<'list'|'grid'>((url.get('view') as 'list'|'grid')||'list'); const [showFilters,setShowFilters]=useState(false); const perPage=url.get('per_page')||'10'; const sort=url.get('sort')||''; const direction=(url.get('direction')||'asc') as 'asc'|'desc';
 const visit=(extra:any={})=>router.get(route('purchase-orders.index'),{...filters,per_page:perPage,view:viewMode,...extra},{preserveState:true,replace:true});
 const clear=()=>{const empty={search:'',vendor_id:'',warehouse_id:'',order_status:'',billing_status:'',date_range:''};setFilters(empty);router.get(route('purchase-orders.index'),{per_page:perPage,view:viewMode});};
 const active=[filters.vendor_id,filters.warehouse_id,filters.order_status,filters.billing_status,filters.date_range].filter(Boolean).length;
 const status=(v:string)=><span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${classes[v]||classes.draft}`}>{t(label(v))}</span>;
 const columns=[
  {key:'purchase_order_number',header:t('PO / LPO Number'),sortable:true,render:(v:string,o:any)=><button className="text-blue-600 hover:text-blue-700" onClick={()=>router.visit(route('purchase-orders.show',o.id))}>{v}</button>},
  {key:'vendor',header:t('Vendor'),render:(v:any)=>v?.name||'-'},
  {key:'order_date',header:t('Order Date'),sortable:true},
  {key:'expected_delivery_date',header:t('Delivery Date'),sortable:true,render:(v:string,o:any)=>{const late=v&&new Date(v)<new Date()&&!['closed','cancelled','rejected'].includes(o.order_status);return <div className={late?'text-red-600':''}>{v||'-'}{late&&<div className="text-xs font-medium">{t('Overdue')}</div>}</div>}},
  {key:'total_amount',header:t('Total Amount'),sortable:true,render:(v:any,o:any)=>`${Number(v).toFixed(2)} ${o.currency_code}`},
  {key:'order_status',header:t('Order Status'),sortable:true,render:status},
  {key:'billing_status',header:t('Billing Status'),sortable:true,render:status},
  {key:'actions',header:t('Actions'),render:(_:any,o:any)=><TooltipProvider><Tooltip delayDuration={0}><TooltipTrigger asChild><Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-green-600" onClick={()=>router.visit(route('purchase-orders.show',o.id))}><Eye className="h-4 w-4"/></Button></TooltipTrigger><TooltipContent>{t('View')}</TooltipContent></Tooltip></TooltipProvider>}
 ];
 const empty=<NoRecordsFound icon={ShoppingCart} title={t('No purchase orders found')} description={t('Get started by creating your first purchase order.')} hasFilters={!!(filters.search||active)} onClearFilters={clear} createPermission="create-purchase-orders" onCreateClick={()=>router.visit(route('purchase-orders.create'))} createButtonText={t('Create Purchase Order')}/>;
 return <AuthenticatedLayout breadcrumbs={[{label:t('Purchase Orders / LPO')}]} pageTitle={t('Manage Purchase Orders / LPO')} pageActions={<TooltipProvider>{auth.user?.permissions?.includes('create-purchase-orders')&&<Tooltip delayDuration={0}><TooltipTrigger asChild><Button size="sm" onClick={()=>router.visit(route('purchase-orders.create'))}><Plus className="h-4 w-4"/></Button></TooltipTrigger><TooltipContent>{t('Create')}</TooltipContent></Tooltip>}</TooltipProvider>}>
  <Head title={t('Purchase Orders / LPO')}/><Card className="shadow-sm">
   <CardContent className="border-b bg-gray-50/50 p-6"><div className="flex items-center justify-between gap-4"><div className="max-w-md flex-1"><SearchInput value={filters.search} onChange={v=>setFilters({...filters,search:v})} onSearch={()=>visit()} placeholder={t('Search by PO / LPO number...')}/></div><div className="flex items-center gap-3"><ListGridToggle currentView={viewMode} routeName="purchase-orders.index" filters={{...filters,per_page:perPage}} onViewChange={setViewMode}/><PerPageSelector routeName="purchase-orders.index" filters={{...filters,view:viewMode}}/><div className="relative"><FilterButton showFilters={showFilters} onToggle={()=>setShowFilters(!showFilters)}/>{active>0&&<span className="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground">{active}</span>}</div></div></div></CardContent>
   {showFilters&&<CardContent className="border-b bg-blue-50/30 p-6"><div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6"><FilterSelect title={t('Vendor')} value={filters.vendor_id} placeholder={t('Filter by vendor')} items={vendors.map((v:any)=>({value:String(v.id),label:v.name}))} change={v=>setFilters({...filters,vendor_id:v})}/><FilterSelect title={t('Warehouse')} value={filters.warehouse_id} placeholder={t('Filter by warehouse')} items={warehouses.map((w:any)=>({value:String(w.id),label:w.name}))} change={v=>setFilters({...filters,warehouse_id:v})}/><FilterSelect title={t('Order Status')} value={filters.order_status} placeholder={t('Order status')} items={['draft','pending_approval','approved','issued','rejected','cancelled','closed'].map(v=>({value:v,label:t(label(v))}))} change={v=>setFilters({...filters,order_status:v})}/><FilterSelect title={t('Billing Status')} value={filters.billing_status} placeholder={t('Billing status')} items={['unbilled','partially_billed','billed'].map(v=>({value:v,label:t(label(v))}))} change={v=>setFilters({...filters,billing_status:v})}/><div><label className="mb-2 block text-sm font-medium">{t('Date Range')}</label><DateRangePicker value={filters.date_range} onChange={v=>setFilters({...filters,date_range:v})}/></div><div className="flex items-end gap-2"><Button size="sm" onClick={()=>visit()}>{t('Apply')}</Button><Button size="sm" variant="outline" onClick={clear}>{t('Clear')}</Button></div></div></CardContent>}
   <CardContent className="p-0">{viewMode==='list'?<div className="max-h-[70vh] overflow-y-auto"><div className="min-w-[900px]"><DataTable data={orders.data} columns={columns} onSort={field=>visit({sort:field,direction:sort===field&&direction==='asc'?'desc':'asc'})} sortKey={sort} sortDirection={direction} className="rounded-none" emptyState={empty}/></div></div>:<div className="p-4">{orders.data.length?<div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">{orders.data.map((o:any)=><Card key={o.id} className="border"><CardContent className="p-4"><div className="flex justify-between gap-2"><button className="font-semibold text-blue-600" onClick={()=>router.visit(route('purchase-orders.show',o.id))}>{o.purchase_order_number}</button>{status(o.order_status)}</div><p className="mt-3 text-sm font-medium">{o.vendor?.name}</p><p className="mt-1 text-sm text-muted-foreground">{o.order_date}</p><p className="mt-4 text-lg font-semibold">{Number(o.total_amount).toFixed(2)} {o.currency_code}</p><div className="mt-3">{status(o.billing_status)}</div></CardContent></Card>)}</div>:empty}</div>}<Pagination data={orders} routeName="purchase-orders.index" filters={{...filters,view:viewMode}}/></CardContent>
  </Card>
 </AuthenticatedLayout>
}

function FilterSelect({title,value,placeholder,items,change}:any){return <div><label className="mb-2 block text-sm font-medium">{title}</label><Select value={value} onValueChange={change}><SelectTrigger><SelectValue placeholder={placeholder}/></SelectTrigger><SelectContent>{items.map((x:any)=><SelectItem key={x.value} value={x.value}>{x.label}</SelectItem>)}</SelectContent></Select></div>}
