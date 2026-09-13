<?php
if (!isAdmin()) { echo '<p class="text-red-500 font-bold p-8">Akses ditolak.</p>'; return; }
$qa_today = date('Y-m-d');
$qa_month = (int)date('n');
$qa_year  = (int)date('Y');
$qa_months = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$qa_user   = 'admin';
$qa_uid    = (int)($_SESSION['user']['id'] ?? 0);
?>
<div id="page-qa-panel" class="hidden animate-fade-in-up">

<!-- Header -->
<div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-rose-600 flex items-center justify-center shadow-md">
      <i class="fi fi-sr-flask text-white text-lg"></i>
    </div>
    <div>
      <h1 class="text-2xl font-bold text-gray-900 tracking-tight">QA Testing Panel</h1>
      <p class="text-sm text-gray-500">Simulasi semua fitur role Pegawai &mdash; tanpa akun dummy</p>
    </div>
  </div>
  <div class="flex items-center gap-2 bg-orange-50 border border-orange-200 text-orange-700 text-sm font-semibold px-4 py-2.5 rounded-xl">
    <i class="fi fi-sr-shield-check"></i><span>Admin Preview Mode</span>
  </div>
</div>

<!-- Status Bar -->
<div id="qa-status-bar" class="hidden mb-6 p-4 rounded-2xl border font-medium text-sm flex items-center gap-3"></div>

<!-- Tab Nav -->
<div class="flex flex-wrap gap-2 mb-8 bg-white p-2 rounded-2xl shadow-sm border border-gray-100">
  <button onclick="qaTab('attendance')" id="qa-btn-attendance" class="qa-nav active flex-1 min-w-max py-2.5 px-4 rounded-xl font-semibold text-sm bg-orange-500 text-white shadow-md flex items-center justify-center gap-2 transition-all">
    <i class="fi fi-sr-webcam"></i> Absensi
  </button>
  <button onclick="qaTab('daily')" id="qa-btn-daily" class="qa-nav flex-1 min-w-max py-2.5 px-4 rounded-xl font-semibold text-sm text-gray-600 hover:bg-orange-50 hover:text-orange-600 flex items-center justify-center gap-2 transition-all">
    <i class="fi fi-sr-file-edit"></i> Laporan Harian
  </button>
  <button onclick="qaTab('monthly')" id="qa-btn-monthly" class="qa-nav flex-1 min-w-max py-2.5 px-4 rounded-xl font-semibold text-sm text-gray-600 hover:bg-orange-50 hover:text-orange-600 flex items-center justify-center gap-2 transition-all">
    <i class="fi fi-sr-document-signed"></i> Laporan Bulanan
  </button>
  <button onclick="qaTab('rekap')" id="qa-btn-rekap" class="qa-nav flex-1 min-w-max py-2.5 px-4 rounded-xl font-semibold text-sm text-gray-600 hover:bg-orange-50 hover:text-orange-600 flex items-center justify-center gap-2 transition-all">
    <i class="fi fi-sr-list-check"></i> Rekap Saya
  </button>
  <button onclick="qaTab('api')" id="qa-btn-api" class="qa-nav flex-1 min-w-max py-2.5 px-4 rounded-xl font-semibold text-sm text-gray-600 hover:bg-orange-50 hover:text-orange-600 flex items-center justify-center gap-2 transition-all">
    <i class="fi fi-sr-code-simple"></i> API Checker
  </button>
</div>

<!-- ABSENSI TAB -->
<div id="qa-pane-attendance" class="qa-pane">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fi fi-sr-info text-orange-500"></i> Status Absensi Hari Ini
      </h3>
      <div class="space-y-3">
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl"><span class="text-sm text-gray-500 w-32">Tanggal</span><span class="text-sm font-bold text-gray-800"><?php echo date('d F Y'); ?></span></div>
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl"><span class="text-sm text-gray-500 w-32">Sebagai</span><span class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($qa_user); ?></span></div>
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl"><span class="text-sm text-gray-500 w-32">Status</span><span id="qa-att-status" class="text-sm font-bold px-3 py-1 rounded-full bg-gray-100 text-gray-600">Memuat...</span></div>
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl"><span class="text-sm text-gray-500 w-32">Jam Masuk</span><span id="qa-jam-in" class="text-sm font-bold text-gray-800">-</span></div>
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl"><span class="text-sm text-gray-500 w-32">Jam Pulang</span><span id="qa-jam-out" class="text-sm font-bold text-gray-800">-</span></div>
      </div>
      <div class="mt-5 flex gap-3">
        <a href="/presensi-masuk"  class="flex-1 py-2.5 px-4 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl text-sm text-center flex items-center justify-center gap-2 transition-all shadow-md">
          <i class="fi fi-sr-sign-in-alt"></i> Presensi Masuk
        </a>
        <a href="/presensi-pulang"  class="flex-1 py-2.5 px-4 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl text-sm text-center flex items-center justify-center gap-2 transition-all shadow-md">
          <i class="fi fi-sr-sign-out-alt"></i> Presensi Pulang
        </a>
      </div>
      <p class="text-xs text-gray-400 mt-3 text-center">Simulasi presensi di halaman yang sama</p>
      
      <!-- Setup Wajah Admin -->
      <div class="mt-4 p-4 border border-blue-100 bg-blue-50 rounded-xl">
        <p class="text-xs font-semibold text-blue-800 mb-2">Belum mendaftarkan wajah? (Wajib untuk tes presensi)</p>
        
        <div id="qa-cam-container" class="hidden mb-3 relative rounded-xl overflow-hidden bg-black aspect-[4/3]">
            <video id="qa-video" autoplay playsinline class="w-full h-full object-cover transform scale-x-[-1]"></video>
            <canvas id="qa-canvas" class="hidden"></canvas>
            <button id="qa-btn-snap" class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-white text-blue-600 rounded-full px-4 py-2 font-bold shadow-lg hover:bg-gray-50 flex items-center gap-2 text-sm z-10">
                <i class="fi fi-sr-camera"></i> Ambil Foto
            </button>
        </div>
        
        <div class="flex items-center gap-2" id="qa-action-buttons">
            <button id="qa-btn-start-cam" class="w-full py-2 bg-white border border-blue-200 text-blue-700 hover:bg-blue-100 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center justify-center gap-2">
                <i class="fi fi-sr-camera"></i> Buka Kamera
            </button>
        </div>
        
        <p id="qa-face-status" class="text-[10px] text-blue-600 mt-2 text-center hidden"></p>
      </div>
    </div>
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fi fi-sr-calendar-check text-orange-500"></i> Riwayat Absensi Terbaru
      </h3>
      <div id="qa-recent-att" class="min-h-48 flex items-center justify-center">
        <div class="text-center"><i class="fi fi-sr-spinner text-2xl text-orange-300 animate-spin block mb-2"></i><span class="text-sm text-gray-400">Memuat...</span></div>
      </div>
      <button onclick="qaLoadRecentAtt()" class="mt-4 w-full py-2.5 px-4 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 font-semibold rounded-xl text-sm flex items-center justify-center gap-2 transition-all">
        <i class="fi fi-sr-refresh"></i> Refresh
      </button>
    </div>
  </div>
</div>

<!-- LAPORAN HARIAN TAB -->
<div id="qa-pane-daily" class="qa-pane hidden">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fi fi-sr-pencil text-orange-500"></i> Isi / Edit Laporan Harian
      </h3>
      <form id="qa-daily-form" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tanggal</label>
          <input type="date" id="qa-daily-date" value="<?php echo $qa_today; ?>" max="<?php echo $qa_today; ?>"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:ring-2 focus:ring-orange-500">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Isi Laporan <span class="text-red-500">*</span></label>
          <textarea id="qa-daily-content" rows="7"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 resize-none focus:ring-2 focus:ring-orange-500"
            placeholder="Deskripsikan kegiatan hari ini..."></textarea>
          <p class="text-xs text-gray-400 mt-1" id="qa-char-count">0 karakter</p>
        </div>
        <div class="flex gap-3">
          <button type="button" onclick="qaLoadDaily()"
            class="py-2.5 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-sm flex items-center gap-2 transition-all">
            <i class="fi fi-sr-refresh"></i> Muat
          </button>
          <button type="submit" id="qa-save-daily"
            class="flex-1 py-2.5 px-4 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-xl text-sm flex items-center justify-center gap-2 transition-all shadow-md">
            <i class="fi fi-sr-disk"></i> Simpan
          </button>
        </div>
      </form>
    </div>
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fi fi-sr-eye text-orange-500"></i> Riwayat Laporan Harian
      </h3>
      <div class="mb-4 flex gap-2">
        <select id="qa-dh-month" class="flex-1 border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50">
          <?php foreach($qa_months as $m=>$mn): ?><option value="<?php echo $m; ?>" <?php echo ($m==$qa_month)?'selected':''; ?>><?php echo $mn; ?></option><?php endforeach; ?>
        </select>
        <select id="qa-dh-year" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50">
          <?php for($y=$qa_year;$y>=$qa_year-2;$y--): ?><option value="<?php echo $y; ?>"><?php echo $y; ?></option><?php endfor; ?>
        </select>
        <button onclick="qaLoadDailyHistory()" class="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-xl text-sm">
          <i class="fi fi-sr-search"></i>
        </button>
      </div>
      <div id="qa-daily-history" class="space-y-2 max-h-72 overflow-y-auto custom-scrollbar pr-1">
        <p class="text-sm text-gray-400 text-center py-8">Pilih bulan lalu klik search</p>
      </div>
    </div>
  </div>
</div>

<!-- LAPORAN BULANAN TAB -->
<div id="qa-pane-monthly" class="qa-pane hidden">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fi fi-sr-document-signed text-orange-500"></i> Daftar Laporan Bulanan
      </h3>
      <div id="qa-monthly-list" class="space-y-2 min-h-48">
        <p class="text-sm text-gray-400 text-center py-8">Memuat...</p>
      </div>
      <button onclick="qaLoadMonthlyList()" class="mt-4 w-full py-2.5 px-4 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 font-semibold rounded-xl text-sm flex items-center justify-center gap-2 transition-all">
        <i class="fi fi-sr-refresh"></i> Refresh
      </button>
    </div>
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fi fi-sr-pencil text-orange-500"></i> Buat / Edit Laporan Bulanan
      </h3>
      <form id="qa-monthly-form" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Bulan</label>
            <select id="qa-m-month" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50">
              <?php foreach($qa_months as $m=>$mn): ?><option value="<?php echo $m; ?>" <?php echo ($m==$qa_month)?'selected':''; ?>><?php echo $mn; ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tahun</label>
            <select id="qa-m-year" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50">
              <?php for($y=$qa_year;$y>=$qa_year-2;$y--): ?><option value="<?php echo $y; ?>"><?php echo $y; ?></option><?php endfor; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Ringkasan Pekerjaan <span class="text-red-500">*</span></label>
          <textarea id="qa-m-summary" rows="3" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 resize-none focus:ring-2 focus:ring-orange-500" placeholder="Ringkasan pekerjaan bulan ini..."></textarea>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Hambatan</label>
          <textarea id="qa-m-challenges" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 resize-none focus:ring-2 focus:ring-orange-500" placeholder="Hambatan yang dihadapi (opsional)..."></textarea>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Rencana Bulan Depan</label>
          <textarea id="qa-m-plans" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 resize-none focus:ring-2 focus:ring-orange-500" placeholder="Rencana bulan depan (opsional)..."></textarea>
        </div>
        <button type="submit" id="qa-save-monthly" class="w-full py-2.5 px-4 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-xl text-sm flex items-center justify-center gap-2 transition-all shadow-md">
          <i class="fi fi-sr-disk"></i> Simpan Laporan Bulanan
        </button>
      </form>
    </div>
  </div>
</div>

<!-- REKAP TAB -->
<div id="qa-pane-rekap" class="qa-pane hidden">
  <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i class="fi fi-sr-list-check text-orange-500"></i> Rekap Kehadiran Saya</h3>
      <div class="flex items-center gap-2">
        <select id="qa-r-month" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50">
          <?php foreach($qa_months as $m=>$mn): ?><option value="<?php echo $m; ?>" <?php echo ($m==$qa_month)?'selected':''; ?>><?php echo $mn; ?></option><?php endforeach; ?>
        </select>
        <select id="qa-r-year" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50">
          <?php for($y=$qa_year;$y>=$qa_year-2;$y--): ?><option value="<?php echo $y; ?>"><?php echo $y; ?></option><?php endfor; ?>
        </select>
        <button onclick="qaLoadRekap()" class="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-xl text-sm flex items-center gap-2 transition-all">
          <i class="fi fi-sr-search"></i> Tampilkan
        </button>
      </div>
    </div>
    <div id="qa-rekap-summary" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6 hidden">
      <div class="bg-green-50 border border-green-100 rounded-2xl p-4 text-center"><div id="qa-r-hadir" class="text-2xl font-bold text-green-600">0</div><div class="text-xs text-green-700 mt-1">Hari Hadir</div></div>
      <div class="bg-red-50 border border-red-100 rounded-2xl p-4 text-center"><div id="qa-r-alpha" class="text-2xl font-bold text-red-600">0</div><div class="text-xs text-red-700 mt-1">Tidak Hadir</div></div>
      <div class="bg-yellow-50 border border-yellow-100 rounded-2xl p-4 text-center"><div id="qa-r-late" class="text-2xl font-bold text-yellow-600">0</div><div class="text-xs text-yellow-700 mt-1">Terlambat</div></div>
      <div class="bg-orange-50 border border-orange-100 rounded-2xl p-4 text-center"><div id="qa-r-nodr" class="text-2xl font-bold text-orange-600">0</div><div class="text-xs text-orange-700 mt-1">Laporan Kosong</div></div>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-gray-100">
      <table class="w-full text-sm text-left">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
          <tr><th class="py-3 px-4">Tanggal</th><th class="py-3 px-4">Masuk</th><th class="py-3 px-4">Pulang</th><th class="py-3 px-4">Status</th><th class="py-3 px-4">Lap. Harian</th></tr>
        </thead>
        <tbody id="qa-rekap-tbody" class="divide-y divide-gray-100">
          <tr><td colspan="5" class="py-12 text-center text-gray-400">Pilih bulan dan klik Tampilkan</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- API CHECKER TAB -->
<div id="qa-pane-api" class="qa-pane hidden">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fi fi-sr-bolt text-orange-500"></i> Quick API Tests</h3>
      <div class="space-y-2">
        <?php $tests=[['get_settings','GET Settings'],['get_members','GET Members'],['get_rekap','GET Rekap Kehadiran'],['get_kpi_data','GET KPI Data'],['get_missing_daily_reports','GET Missing Reports'],['get_monthly_reports','GET Monthly Reports']]; foreach($tests as [$a,$l]): ?>
        <button onclick="qaApiTest('<?php echo $a; ?>')"
          class="w-full flex items-center justify-between p-3 bg-gray-50 hover:bg-orange-50 border border-gray-200 hover:border-orange-300 rounded-xl text-sm font-medium text-gray-700 hover:text-orange-700 transition-all group">
          <div class="flex items-center gap-2"><span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-bold">GET</span><?php echo htmlspecialchars($l); ?></div>
          <i class="fi fi-sr-play text-gray-300 group-hover:text-orange-400 transition-colors"></i>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i class="fi fi-sr-code-simple text-orange-500"></i> Response</h3>
        <div id="qa-api-badge" class="hidden px-3 py-1 rounded-full text-xs font-bold"></div>
      </div>
      <div class="flex gap-2 mb-4">
        <input type="text" id="qa-custom-action" placeholder="Custom action name..." class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm bg-gray-50 focus:ring-2 focus:ring-orange-500">
        <button onclick="qaApiTest(document.getElementById('qa-custom-action').value)" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-xl text-sm"><i class="fi fi-sr-play"></i></button>
      </div>
      <div id="qa-api-response" class="bg-gray-900 rounded-2xl p-4 font-mono text-xs text-green-400 min-h-64 max-h-96 overflow-auto whitespace-pre-wrap">
<span class="text-gray-500">// Response will appear here...</span></div>
      <div class="flex gap-2 mt-3">
        <button onclick="document.getElementById('qa-api-response').innerHTML='<span class=\'text-gray-500\'>// Cleared.</span>';document.getElementById('qa-api-badge').className='hidden';"
          class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-xs font-medium">Clear</button>
        <button onclick="qaExportJson()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-xs font-medium">Export JSON</button>
      </div>
    </div>
  </div>
</div>

</div><!-- /page-qa-panel -->


<script>
(function(){
const QA_UID=<?php echo (int)$qa_uid; ?>;
function setStatus(msg,type){var bar=document.getElementById('qa-status-bar');if(!bar)return;var cls={success:'bg-green-50 border-green-200 text-green-700',error:'bg-red-50 border-red-200 text-red-700',info:'bg-blue-50 border-blue-200 text-blue-700',warning:'bg-yellow-50 border-yellow-200 text-yellow-700'};var ico={success:'fi-sr-check-circle',error:'fi-sr-cross-circle',info:'fi-sr-info',warning:'fi-sr-exclamation'};bar.className='mb-6 p-4 rounded-2xl border font-medium text-sm flex items-center gap-3 '+(cls[type]||cls.info);bar.innerHTML='<i class="fi '+(ico[type]||ico.info)+'"></i><span>'+msg+'</span>';bar.classList.remove('hidden');clearTimeout(bar._t);bar._t=setTimeout(function(){bar.classList.add('hidden');},5000);}
window.qaTab=function(t){document.querySelectorAll('.qa-pane').forEach(function(e){e.classList.add('hidden');});document.querySelectorAll('.qa-nav').forEach(function(b){b.classList.remove('bg-orange-500','text-white','shadow-md');b.classList.add('text-gray-600');});var pe=document.getElementById('qa-pane-'+t);if(pe)pe.classList.remove('hidden');var be=document.getElementById('qa-btn-'+t);if(be){be.classList.add('bg-orange-500','text-white','shadow-md');be.classList.remove('text-gray-600');}if(t==='attendance')qaLoadAtt();if(t==='monthly')qaLoadMonthlyList();};
window.qaLoadAtt=async function(){try{var t=new Date();var fd=new URLSearchParams();fd.append('month',t.getMonth()+1);fd.append('year',t.getFullYear());var r=await fetch('/?ajax=get_rekap',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});var d=await r.json();var rows=d&&d.data||[];var td=t.getFullYear()+'-'+String(t.getMonth()+1).padStart(2,'0')+'-'+String(t.getDate()).padStart(2,'0');var a=rows.find(function(row){return (row.tanggal||row.date)===td;});var s=document.getElementById('qa-att-status');var mi=document.getElementById('qa-jam-in');var mo=document.getElementById('qa-jam-out');var jm=a&&(a.jam_masuk||a.check_in);var jp=a&&(a.jam_pulang||a.check_out);if(jm){s.textContent=jp?'Sudah Pulang':'Sedang Hadir';s.className='text-sm font-bold px-3 py-1 rounded-full '+(jp?'bg-gray-100 text-gray-600':'bg-green-100 text-green-700');mi.textContent=jm.substring(0,5);mo.textContent=jp?jp.substring(0,5):'-';}else{s.textContent='Belum Absen';s.className='text-sm font-bold px-3 py-1 rounded-full bg-yellow-100 text-yellow-700';mi.textContent='-';mo.textContent='-';}}catch(e){var s2=document.getElementById('qa-att-status');if(s2)s2.textContent='Error';}qaLoadRecentAtt();};
window.qaLoadRecentAtt=async function(){var c=document.getElementById('qa-recent-att');if(!c)return;c.innerHTML='<div class="text-center w-full py-4"><i class="fi fi-sr-spinner animate-spin text-orange-400 text-xl block mb-1"></i><span class="text-xs text-gray-400">Memuat...</span></div>';try{var t=new Date();var fd=new URLSearchParams();fd.append('month',t.getMonth()+1);fd.append('year',t.getFullYear());var r=await fetch('/?ajax=get_rekap',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});var d=await r.json();var rows=(d&&d.data||[]).slice(-7).reverse();if(!rows.length){c.innerHTML='<p class="text-sm text-gray-400 text-center py-8 w-full">Belum ada data bulan ini</p>';return;}c.innerHTML='<div class="space-y-2 w-full">'+rows.map(function(row){var h=!!(row.check_in||row.jam_masuk);return'<div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl text-xs"><span class="font-semibold text-gray-700">'+(row.tanggal||row.date||'-')+'</span><span class="text-green-600">'+(row.jam_masuk||row.check_in||'-')+'</span><span class="text-red-500">'+(row.jam_pulang||row.check_out||'-')+'</span><span class="px-2 py-0.5 rounded-full font-bold '+(h?'bg-green-100 text-green-700':'bg-red-100 text-red-600')+'">'+(h?'Hadir':'Alpha')+'</span></div>';}).join('')+'</div>';}catch(e){c.innerHTML='<p class="text-sm text-red-400 text-center py-8 w-full">Error: '+e.message+'</p>';}};
var dc=document.getElementById('qa-daily-content');if(dc)dc.addEventListener('input',function(){var el=document.getElementById('qa-char-count');if(el)el.textContent=this.value.length+' karakter';});
window.qaLoadDaily=async function(){var date=document.getElementById('qa-daily-date')&&document.getElementById('qa-daily-date').value;if(!date)return;try{var fd=new URLSearchParams();fd.append('user_id',QA_UID);fd.append('date',date);var r=await fetch('/?ajax=get_daily_report_detail',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});var d=await r.json();var dr=d&&d.data&&d.data.daily_report||d&&d.data;if(dr&&dr.content){document.getElementById('qa-daily-content').value=dr.content;document.getElementById('qa-char-count').textContent=dr.content.length+' karakter';setStatus('Laporan '+date+' dimuat','info');}else{setStatus('Belum ada laporan untuk '+date,'warning');}}catch(e){setStatus('Error: '+e.message,'error');}};
var df=document.getElementById('qa-daily-form');if(df)df.addEventListener('submit',async function(e){e.preventDefault();var date=document.getElementById('qa-daily-date').value;var content=document.getElementById('qa-daily-content').value.trim();if(!content){setStatus('Isi laporan tidak boleh kosong','warning');return;}var btn=document.getElementById('qa-save-daily');btn.disabled=true;btn.innerHTML='<i class="fi fi-sr-spinner animate-spin"></i> Menyimpan...';try{var csrf=document.querySelector('meta[name="csrf-token"]')&&document.querySelector('meta[name="csrf-token"]').getAttribute('content')||'';var fd=new FormData();fd.append('date',date);fd.append('content',content);var r=await fetch('/?ajax=save_daily_report',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:fd});var d=await r.json();if(d.ok||d.status==='success'){setStatus('Laporan harian berhasil disimpan!','success');qaLoadDailyHistory();}else{setStatus('Gagal: '+(d.message||'Kesalahan tidak diketahui'),'error');}}catch(err){setStatus('Error: '+err.message,'error');}finally{btn.disabled=false;btn.innerHTML='<i class="fi fi-sr-disk"></i> Simpan';}});
window.qaLoadDailyHistory=async function(){var m=document.getElementById('qa-dh-month')&&document.getElementById('qa-dh-month').value;var y=document.getElementById('qa-dh-year')&&document.getElementById('qa-dh-year').value;var c=document.getElementById('qa-daily-history');if(!c)return;c.innerHTML='<p class="text-xs text-gray-400 text-center py-4">Memuat...</p>';try{var fd=new URLSearchParams();fd.append('month',m);fd.append('year',y);var r=await fetch('/?ajax=get_rekap',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});var d=await r.json();var rows=(d&&d.data||[]).filter(function(row){return row.daily_report||row.check_in||row.jam_masuk;});if(!rows.length){c.innerHTML='<p class="text-xs text-gray-400 text-center py-6">Tidak ada laporan ditemukan</p>';return;}c.innerHTML=rows.map(function(row){var dr=row.daily_report;var content=dr&&dr.content||'-';var approved=dr&&dr.status==='approved';return'<div class="p-3 border border-gray-100 rounded-xl hover:border-orange-200 transition-colors"><div class="flex items-center justify-between mb-1"><span class="text-xs font-bold text-gray-700">'+(row.tanggal||row.date||'-')+'</span><span class="text-xs px-2 py-0.5 rounded-full font-semibold '+(approved?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700')+'">'+(approved?'Approved':dr&&dr.status||'Pending')+'</span></div><p class="text-xs text-gray-500 truncate">'+content.substring(0,80)+'</p></div>';}).join('');}catch(e){c.innerHTML='<p class="text-xs text-red-400 text-center py-4">Error: '+e.message+'</p>';}};
window.qaLoadMonthlyList=async function(){var c=document.getElementById('qa-monthly-list');if(!c)return;c.innerHTML='<p class="text-sm text-gray-400 text-center py-8">Memuat...</p>';try{var r=await fetch('/?ajax=get_monthly_reports',{headers:{'X-Requested-With':'XMLHttpRequest'}});var d=await r.json();var rows=d&&d.data||[];if(!rows.length){c.innerHTML='<p class="text-sm text-gray-400 text-center py-8">Belum ada laporan bulanan</p>';return;}var sm={approved:'bg-green-100 text-green-700',disapproved:'bg-red-100 text-red-700',pending:'bg-yellow-100 text-yellow-700'};c.innerHTML=rows.slice(0,8).map(function(row){var m=row.month||row.bulan;var y=row.year||row.tahun;return'<div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl hover:bg-orange-50 transition-colors cursor-pointer" onclick="qaFillMonthly('+m+','+y+')"><div class="min-w-0"><div class="font-semibold text-gray-800 text-sm">'+(row.month_name||(m+'/'+y)||'-')+'</div><div class="text-xs text-gray-500 truncate">'+((row.summary||row.ringkasan||'-').substring(0,50))+'</div></div><span class="text-xs px-2 py-1 rounded-full font-bold ml-3 flex-shrink-0 '+(sm[row.status]||'bg-gray-100 text-gray-600')+'">'+(row.status||'pending')+'</span></div>';}).join('');}catch(e){c.innerHTML='<p class="text-sm text-red-400 text-center py-8">Error: '+e.message+'</p>';}};
window.qaFillMonthly=function(m,y){var mm=document.getElementById('qa-m-month');var yy=document.getElementById('qa-m-year');if(m&&mm)mm.value=m;if(y&&yy)yy.value=y;};
var mf=document.getElementById('qa-monthly-form');if(mf)mf.addEventListener('submit',async function(e){e.preventDefault();var m=document.getElementById('qa-m-month').value;var y=document.getElementById('qa-m-year').value;var s=document.getElementById('qa-m-summary').value.trim();var ch=document.getElementById('qa-m-challenges').value.trim();var pl=document.getElementById('qa-m-plans').value.trim();if(!s){setStatus('Ringkasan pekerjaan wajib diisi','warning');return;}var btn=document.getElementById('qa-save-monthly');btn.disabled=true;btn.innerHTML='<i class="fi fi-sr-spinner animate-spin"></i> Menyimpan...';try{var csrf=document.querySelector('meta[name="csrf-token"]')&&document.querySelector('meta[name="csrf-token"]').getAttribute('content')||'';var fd=new FormData();fd.append('month',m);fd.append('year',y);fd.append('summary',s);fd.append('challenges',ch);fd.append('plans',pl);fd.append('achievements','[]');fd.append('improvements','[]');var r=await fetch('/?ajax=save_monthly_report',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:fd});var d=await r.json();if(d.ok||d.status==='success'){setStatus('Laporan bulanan berhasil disimpan!','success');qaLoadMonthlyList();}else{setStatus('Gagal: '+(d.message||'Kesalahan'),'error');}}catch(err){setStatus('Error: '+err.message,'error');}finally{btn.disabled=false;btn.innerHTML='<i class="fi fi-sr-disk"></i> Simpan Laporan Bulanan';}});
window.qaLoadRekap=async function(){var m=document.getElementById('qa-r-month')&&document.getElementById('qa-r-month').value;var y=document.getElementById('qa-r-year')&&document.getElementById('qa-r-year').value;var tb=document.getElementById('qa-rekap-tbody');if(!tb)return;tb.innerHTML='<tr><td colspan="5" class="py-8 text-center text-gray-400">Memuat...</td></tr>';try{var fd=new URLSearchParams();fd.append('month',m);fd.append('year',y);var r=await fetch('/?ajax=get_rekap',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});var d=await r.json();var rows=d&&d.data||[];if(!rows.length){tb.innerHTML='<tr><td colspan="5" class="py-8 text-center text-gray-400">Tidak ada data</td></tr>';return;}var h=0,a=0,l=0,nd=0;tb.innerHTML=rows.map(function(row){var hadir=!!(row.check_in||row.jam_masuk);var late=row.is_late||row.terlambat;var dr=row.daily_report;var drs=dr&&dr.status||row.daily_report_status;if(hadir)h++;else a++;if(late)l++;if(hadir&&!drs)nd++;return'<tr class="hover:bg-gray-50"><td class="py-2.5 px-4 text-xs font-medium text-gray-800">'+(row.tanggal||row.date||'-')+'</td><td class="py-2.5 px-4 text-xs '+(hadir?'text-green-600 font-semibold':'text-gray-400')+'">'+(row.jam_masuk||row.check_in||'â€”')+'</td><td class="py-2.5 px-4 text-xs '+((row.jam_pulang||row.check_out)?'text-red-500 font-semibold':'text-gray-400')+'">'+(row.jam_pulang||row.check_out||'â€”')+'</td><td class="py-2.5 px-4"><span class="text-xs px-2 py-0.5 rounded-full font-semibold '+(hadir?(late?'bg-yellow-100 text-yellow-700':'bg-green-100 text-green-700'):'bg-red-100 text-red-700')+'">'+(hadir?(late?'Terlambat':'Hadir'):'Alpha')+'</span></td><td class="py-2.5 px-4"><span class="text-xs px-2 py-0.5 rounded-full font-semibold '+(drs==='approved'?'bg-green-100 text-green-700':drs?'bg-yellow-100 text-yellow-700':'bg-gray-100 text-gray-400')+'">'+(drs==='approved'?'Approved':drs?'Pending':'â€”')+'</span></td></tr>';}).join('');var sum=document.getElementById('qa-rekap-summary');if(sum)sum.classList.remove('hidden');['qa-r-hadir','qa-r-alpha','qa-r-late','qa-r-nodr'].forEach(function(id,i){var el=document.getElementById(id);if(el)el.textContent=[h,a,l,nd][i];});}catch(e){tb.innerHTML='<tr><td colspan="5" class="py-8 text-center text-red-400">Error: '+e.message+'</td></tr>';}};
window.qaApiTest=async function(action){var re=document.getElementById('qa-api-response');var ba=document.getElementById('qa-api-badge');if(!action){if(re)re.textContent='// Masukkan action name...';return;}if(re)re.textContent='// Fetching ?ajax='+action+'...\n';if(ba)ba.className='hidden';var start=Date.now();try{var m='GET';var b=null;if(action==='get_rekap'||action==='get_daily_report_detail'){m='POST';b=new URLSearchParams();b.append('month',new Date().getMonth()+1);b.append('year',new Date().getFullYear());b.append('date',new Date().toISOString().split('T')[0]);}var r=await fetch('/?ajax='+action,{method:m,body:b,headers:{'X-Requested-With':'XMLHttpRequest'}});var elapsed=Date.now()-start;var txt=await r.text();var fmt;try{fmt=JSON.stringify(JSON.parse(txt),null,2);}catch(_){fmt=txt;}if(re)re.textContent=fmt;if(ba){ba.className='px-3 py-1 rounded-full text-xs font-bold '+(r.ok?'bg-green-100 text-green-700':'bg-red-100 text-red-700');ba.textContent=r.status+' '+(r.ok?'OK':'ERR')+' Â· '+elapsed+'ms';}}catch(e){if(re)re.textContent='// Error: '+e.message;if(ba){ba.className='px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700';ba.textContent='NETWORK ERROR';}}};
window.qaExportJson=function(){var content=document.getElementById('qa-api-response')&&document.getElementById('qa-api-response').textContent||'{}';var b=new Blob([content],{type:'application/json'});var u=URL.createObjectURL(b);var a=document.createElement('a');a.href=u;a.download='qa-response.json';a.click();URL.revokeObjectURL(u);};
document.addEventListener('DOMContentLoaded',function(){var p=document.getElementById('page-qa-panel');if(p&&!p.classList.contains('hidden'))qaLoadAtt();});
window.qaInitOnShow=function(){qaLoadAtt();};
})();
</script>
