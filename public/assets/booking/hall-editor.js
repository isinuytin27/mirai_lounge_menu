/* лёгкая обёртка вместо прототип-рантайма */
class DCLogic {
  constructor(root){
    this.root = root;
    var vals = this.renderVals ? this.renderVals() : {};
    if (root.dataset && root.dataset.ref && vals[root.dataset.ref]) vals[root.dataset.ref](root);
    root.querySelectorAll('[data-ref]').forEach(function(el){ var f=vals[el.dataset.ref]; if(f) f(el); });
    if (this.componentDidMount) this.componentDidMount();
  }
}

class Component extends DCLogic {
  renderVals() {
    const R = {};
    const mk = k => el => { this[k] = el; };
    ['Root','Stage','World','ImgA','ImgB','Layer','Panel','List','Count','Editor','Empty',
     'SaveMsg','ImportInput','PhotoInput','PhotoWrap','PhotoImg','PhotoHint',
     'FLabel','FDesc','FPhone','FW','FWnum','FH','FHnum','FRadius','RadiusWrap','RadiusVal','WVal','HVal',
     'NoteList','NoteCount','NoteViewLab','NoteEditor','NText','NPhotoWrap','NPhotoImg','NPhotoHint','NPhotoInput',
     'HotList','HotCount','HotEditor','HLabel','HTitle','HDesc','HPhone','HPhoneWrap','HFade']
      .forEach(n => { R['set'+n] = mk(n.charAt(0).toLowerCase()+n.slice(1)); });
    return R;
  }

  componentDidMount() {
    this.AR = 1672 / 941;
    this.view = 'a';
    this.selId = null;
    this.selNoteId = null;
    this.selHotId = null;
    this.shapeEls = {};
    this.noteEls = [];
    this.hotEls = [];
    this.load();
    this.loadNotes();
    this.loadHots();
    this.renderList();
    this.renderLayer();
    this.renderHotList();
    this.bindStatic();
    this.initPointer();
    this._onResize = () => this.layout();
    window.addEventListener('resize', this._onResize);
    this.layout();
    this.setView('a');
    this.fetchConfig();   // перекрыть seed данными из БД
  }
  componentWillUnmount(){ window.removeEventListener('resize', this._onResize); }

  // ---------- data ----------
  seed() {
    const T = [
      ['11',0.305,0.315,0.115,0.0856],['12',0.385,0.270,0.105,0.0645],
      ['13',0.552,0.385,0.16,0.0707],['14',0.300,0.430,0.105,0.0781],
      ['15',0.300,0.515,0.105,0.0781],['16',0.298,0.585,0.10,0.0744],
      ['17',0.415,0.415,0.115,0.0625],['18',0.445,0.485,0.115,0.0856],
    ];
    return T.map(t => ({
      id:'t'+t[0], label:'Стол '+t[0], status:'busy', shape:'poly',
      x:t[1], y:t[2], w:t[3], h:t[4], radius:14,
      points:'0,51 63,0 100,49 37,100',
      desc:'Lounge · бронирование стола заранее', phone:'+74242218080', photo:''
    }));
  }
  // Источник — наш API. До ответа показываем seed; fetchConfig() перекрывает данными БД.
  load() { this.data = this.seed(); }
  // Подтянуть конфиг зала из БД и перерисовать.
  fetchConfig() {
    return fetch('/api/booking/hall')
      .then(r => r.json())
      .then(j => {
        if (!j || !j.ok) return;
        if (Array.isArray(j.tables) && j.tables.length) this.data = j.tables;
        if (Array.isArray(j.zones) && j.zones.length) this.hots = j.zones;
        this.notes = Array.isArray(j.notes) ? j.notes : [];
        this.renderList(); this.renderLayer(); this.renderHots(); this.renderHotList();
        this.renderNotes(); this.setView(this.view || 'a');
      })
      .catch(() => {});
  }
  // Сохранение в БД (POST). Вызывается часто (drag/resize) — уже дебаунсится в initPointer.
  save(msg) {
    const body = JSON.stringify({
      positions: this.data, hotspots: this.hots || [], notes: this.notes || [],
      _csrf: (window.__CSRF || '')
    });
    fetch('/admin/booking/hall', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body })
      .then(r => r.json())
      .then(j => this.flashSave(!!(j && j.ok), null, msg))
      .catch(e => this.flashSave(false, e, msg));
    return true;
  }
  // Сообщение о сохранении/ошибке в шапке (общее для позиций и заметок).
  flashSave(ok, err, msg) {
    if (!this.saveMsg) return;
    if (ok) {
      this.saveMsg.textContent = msg || 'Сохранено'; this.saveMsg.style.color='var(--jade)';
      clearTimeout(this._st); this._st=setTimeout(()=>{ if(this.saveMsg){this.saveMsg.textContent='Сохранено';this.saveMsg.style.color='var(--washi-faint)';} },1200);
    } else {
      const quota = err && (err.name === 'QuotaExceededError' || /quota/i.test(String(err)));
      this.saveMsg.textContent = quota ? 'Нет места — убери фото' : 'Ошибка сохранения';
      this.saveMsg.style.color = 'var(--neon)';
      clearTimeout(this._st);
      if (quota) console.warn('[editor] localStorage переполнен: фото в base64 слишком тяжёлые. Уменьшите/удалите фото или используйте Экспорт JSON.');
    }
  }
  get sel(){ return this.data.find(p => p.id === this.selId) || null; }

  // ========== ЗАМЕТКИ (отдельные для вида A и вида B; читаются картой) ==========
  loadNotes() { this.notes = []; }
  saveNotes(msg) { return this.save(msg); } // заметки сохраняются тем же POST, что и позиции/зоны
  esc(s){ return String(s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
  notesForView(){ return (this.notes||[]).filter(n => (n.view||'a') === this.view); }

  renderNotes() {
    (this.noteEls||[]).forEach(el => el.remove());
    this.noteEls = [];
    this.notesForView().forEach(n => { const el = this.buildNotePin(n); this.layer.appendChild(el); this.noteEls.push(el); });
    this.styleNotes();
  }
  buildNotePin(n) {
    const el = document.createElement('div');
    el.className = 'ad-note'; el.dataset.note = n.id; el.dataset.view = n.view || 'a';
    el.style.cssText = 'position:absolute;transform:translate(-50%,-100%);transform-origin:bottom center;cursor:grab;z-index:3;';
    el.innerHTML = '<div class="ad-note-pin" style="position:relative;width:28px;height:28px;border-radius:50% 50% 50% 2px;transform:rotate(45deg);background:rgba(13,13,16,.94);border:1.5px solid var(--neon);box-shadow:var(--glow-neon);display:flex;align-items:center;justify-content:center;">'
      + '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#F5007D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transform:rotate(-45deg);"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></div>';
    this.placeNotePin(el, n);
    return el;
  }
  placeNotePin(el, n){ el.style.left = (n.x*100)+'%'; el.style.top = (n.y*100)+'%'; }
  styleNotes() {
    (this.noteEls||[]).forEach(el => {
      const sel = el.dataset.note === this.selNoteId;
      el.style.zIndex = sel ? '5' : '3';
      const pin = el.querySelector('.ad-note-pin');
      if (pin) pin.style.boxShadow = sel ? '0 0 0 2px #fff, var(--glow-neon)' : 'var(--glow-neon)';
    });
  }
  renderNoteList() {
    if (!this.noteList) return;
    this.noteList.innerHTML = '';
    const list = this.notesForView();
    if (this.noteCount) this.noteCount.textContent = list.length;
    list.forEach(n => {
      const b = document.createElement('button');
      b.className = 'ad-li'; b.dataset.note = n.id;
      b.dataset.sel = n.id === this.selNoteId ? '1' : '0';
      const has = n.text && n.text.trim();
      const label = has ? this.esc(n.text) : '<i style="color:var(--washi-faint);">пустая заметка</i>';
      b.innerHTML = '<span style="width:9px;height:9px;border-radius:50%;flex-shrink:0;background:#F5007D;box-shadow:0 0 7px #F5007D;"></span>'
        + '<span style="flex:1;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + label + '</span>'
        + (n.photo ? '<span style="color:var(--washi-faint);font-size:12px;">▣</span>' : '');
      b.addEventListener('click', () => this.selectNote(n.id));
      this.noteList.appendChild(b);
    });
  }
  renderNoteListSel(){ if (this.noteList) this.noteList.querySelectorAll('.ad-li').forEach(b => b.dataset.sel = b.dataset.note===this.selNoteId ? '1':'0'); }

  addNote() {
    const n = { id:'n'+Date.now().toString(36)+Math.floor(Math.random()*1000), x:0.5, y:0.5, text:'', photo:'', view:this.view };
    this.notes.push(n);
    const el = this.buildNotePin(n); this.layer.appendChild(el); this.noteEls.push(el);
    this.renderNoteList();
    this.saveNotes('Заметка добавлена');
    this.selectNote(n.id);
  }
  removeNote() {
    const n = this.selNote; if (!n) return;
    this.notes = this.notes.filter(x => x.id !== n.id);
    const el = (this.noteEls||[]).find(e => e.dataset.note === n.id);
    if (el) { el.remove(); this.noteEls = this.noteEls.filter(e => e !== el); }
    this.selNoteId = null;
    this.renderNoteList(); this.showPanel(); this.saveNotes('Заметка удалена');
  }
  populateNote(n) {
    if (this.nText) this.nText.value = n.text || '';
    if (n.photo) { this.nPhotoImg.src = n.photo; this.nPhotoImg.style.display='block'; this.nPhotoHint.style.display='none'; }
    else { this.nPhotoImg.removeAttribute('src'); this.nPhotoImg.style.display='none'; this.nPhotoHint.style.display='block'; }
  }
  readNotePhoto(file) {
    const n = this.selNote; if (!file || !n) return;
    const rd = new FileReader();
    rd.onload = () => {
      const img = new Image();
      img.onload = () => {
        const max = 1000, s = Math.min(1, max / Math.max(img.width, img.height));
        const cv = document.createElement('canvas');
        cv.width = Math.round(img.width * s); cv.height = Math.round(img.height * s);
        cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
        const data = cv.toDataURL('image/jpeg', 0.82);
        n.photo = data;
        this.nPhotoImg.src = data; this.nPhotoImg.style.display='block'; this.nPhotoHint.style.display='none';
        this.renderNoteList(); this.saveNotes('Фото добавлено');
      };
      img.onerror = () => this.flashSave(false, null, '');
      img.src = rd.result;
    };
    rd.readAsDataURL(file);
  }

  // ========== ЗОНЫ-КНОПКИ (Lounge, ПК-зона, …) — позиции отдельно для вида A и B ==========
  seedHots() {
    return [
      { id:'lounge', label:'Lounge', kind:'zone', accent:'neon', title:'Зона со столами',
        desc:'Столы и места для отдыха. Можно забронировать заранее.', phone:'',
        a:[0.515,0.498], b:[0.418,0.452], pref:0, hideOnTables:true },
      { id:'pc', label:'ПК-зона', kind:'phone', accent:'cyan', title:'Игровые станции',
        desc:'Бронирование по телефону', phone:'+7 4242 21-80-80',
        a:[0.338,0.342], b:[0.717,0.560], pref:0, hideOnTables:false }
    ];
  }
  loadHots() { this.hots = this.seedHots(); } // seed до fetchConfig()
  hotColor(h){ return h.accent==='cyan' ? '#2DE2E6' : '#F5007D'; }
  renderHots() {
    (this.hotEls||[]).forEach(el => el.remove());
    this.hotEls = [];
    (this.hots||[]).forEach(h => { const el = this.buildHotPin(h); this.layer.appendChild(el); this.hotEls.push(el); });
    this.styleHots();
  }
  buildHotPin(h) {
    const coord = h[this.view] || h.a || [0.5,0.5];
    const c = this.hotColor(h);
    const el = document.createElement('div');
    el.className = 'ad-hot'; el.dataset.hot = h.id;
    el.style.cssText = 'position:absolute;transform:translate(-50%,-50%);cursor:grab;z-index:4;';
    el.innerHTML = '<div class="ad-hot-pill" style="display:flex;align-items:center;gap:7px;background:rgba(13,13,16,.92);border:1px solid '+c+';padding:6px 11px;border-radius:999px;box-shadow:0 0 14px '+c+'66;white-space:nowrap;">'
      + '<span style="width:9px;height:9px;border-radius:50%;background:'+c+';box-shadow:0 0 7px '+c+';"></span>'
      + '<span style="font-weight:700;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#fff;">'+this.esc(h.label||h.id)+'</span></div>';
    el.style.left = (coord[0]*100)+'%'; el.style.top = (coord[1]*100)+'%';
    return el;
  }
  placeHotPin(el, h){ const c = h[this.view] || h.a || [0.5,0.5]; el.style.left = (c[0]*100)+'%'; el.style.top = (c[1]*100)+'%'; }
  styleHots() {
    (this.hotEls||[]).forEach(el => {
      const sel = el.dataset.hot === this.selHotId;
      el.style.zIndex = sel ? '6' : '4';
      const pill = el.querySelector('.ad-hot-pill');
      if (pill) pill.style.outline = sel ? '2px solid #fff' : 'none';
    });
  }
  renderHotList() {
    if (!this.hotList) return;
    this.hotList.innerHTML = '';
    if (this.hotCount) this.hotCount.textContent = (this.hots||[]).length;
    (this.hots||[]).forEach(h => {
      const b = document.createElement('button');
      b.className = 'ad-li'; b.dataset.hot = h.id;
      b.dataset.sel = h.id === this.selHotId ? '1' : '0';
      const c = this.hotColor(h);
      b.innerHTML = '<span style="width:9px;height:9px;border-radius:50%;flex-shrink:0;background:'+c+';box-shadow:0 0 7px '+c+';"></span>'
        + '<span style="flex:1;font-weight:700;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'+this.esc(h.label||h.id)+'</span>'
        + '<span style="color:var(--washi-faint);font-size:12px;">'+(h.kind==='phone'?'☎':'◇')+'</span>';
      b.addEventListener('click', () => this.selectHot(h.id));
      this.hotList.appendChild(b);
    });
  }
  renderHotListSel(){ if (this.hotList) this.hotList.querySelectorAll('.ad-li').forEach(b => b.dataset.sel = b.dataset.hot===this.selHotId ? '1':'0'); }
  populateHot(h) {
    if (this.hLabel) this.hLabel.value = h.label || '';
    if (this.hTitle) this.hTitle.value = h.title || '';
    if (this.hDesc) this.hDesc.value = h.desc || '';
    if (this.hPhone) this.hPhone.value = h.phone || '';
    if (this.hFade) this.hFade.checked = !!h.hideOnTables;
    if (this.hPhoneWrap) this.hPhoneWrap.style.display = (h.kind === 'phone') ? 'block' : 'none';
    this.root.querySelectorAll('[data-hkind]').forEach(b => b.dataset.on = b.dataset.hkind===(h.kind||'zone') ? '1':'0');
    this.root.querySelectorAll('[data-haccent]').forEach(b => b.dataset.on = b.dataset.haccent===(h.accent||'neon') ? '1':'0');
    this.root.querySelectorAll('[data-hpref]').forEach(b => b.dataset.on = (+b.dataset.hpref)===(h.pref||0) ? '1':'0');
  }
  updateHot(fn, opts) {
    const h = this.selHot; if (!h) return;
    fn(h);
    this.renderHots();
    if (opts && opts.list) this.renderHotList();
    this.save();
  }
  addHot() {
    const id = 'z' + Date.now().toString(36);
    const h = { id, label:'Новая зона', kind:'zone', accent:'neon', title:'Заголовок', desc:'Описание зоны',
      phone:'', a:[0.5,0.5], b:[0.5,0.5], pref:0, hideOnTables:false };
    this.hots.push(h);
    this.renderHots(); this.renderHotList(); this.save('Зона добавлена');
    this.selectHot(id);
  }
  removeHot() {
    const h = this.selHot; if (!h) return;
    this.hots = this.hots.filter(x => x.id !== h.id);
    this.selHotId = null;
    this.renderHots(); this.renderHotList(); this.showPanel(); this.save('Зона удалена');
  }

  statusColor(s){ return s==='free'?'var(--jade)':s==='live'?'var(--cyan)':'var(--neon)'; }
  statusRaw(s){ return s==='free'?'#3DDC84':s==='live'?'#2DE2E6':'#F5007D'; }

  // ---------- layout ----------
  layout() {
    const r = this.stage.getBoundingClientRect();
    const pad = Math.min(40, r.width*0.04);
    let w = r.width - pad*2, h = w/this.AR;
    const availH = r.height - pad*2;
    if (h > availH){ h = availH; w = h*this.AR; }
    this.baseW = w; this.baseH = h;
    this.world.style.width = w+'px'; this.world.style.height = h+'px';
    this.world.style.left = (r.width-w)/2+'px';
    this.world.style.top = (r.height-h)/2+'px';
  }

  setView(v) {
    this.view = v;
    this.imgA.style.opacity = v==='a'?'1':'0';
    this.imgB.style.opacity = v==='b'?'1':'0';
    this.root.querySelectorAll('[data-view]').forEach(b => b.dataset.on = b.dataset.view===v ? '1':'0');
    // заметки привязаны к виду — показываем только заметки текущего вида
    if (this.noteViewLab) this.noteViewLab.textContent = v==='a' ? 'вида A' : 'вида B';
    if (this.selNote && (this.selNote.view||'a') !== v) this.selNoteId = null;
    this.renderNotes();
    this.renderNoteList();
    this.renderHots();          // хотспоты зон — на своих координатах для текущего вида
    this.updateShapeVis();      // позиции-столы видны только на виде B
    this.showPanel();
  }

  // Позиции (столы) показываем только на виде B.
  updateShapeVis() {
    const show = this.view === 'b';
    Object.keys(this.shapeEls).forEach(id => { if (this.shapeEls[id]) this.shapeEls[id].style.display = show ? '' : 'none'; });
    if (!show) this.removeHandles();
    else if (this.sel) { this.removeHandles(); this.buildHandles(this.sel); }
  }

  // ---------- render shapes ----------
  renderLayer() {
    this.layer.innerHTML = '';
    this.shapeEls = {};
    this.hotEls = [];   // слой очищен — пины зон тоже; перерисуются renderHots()
    this.data.forEach(p => this.layer.appendChild(this.buildShape(p)));
    this.updateShapeVis();
  }
  buildShape(p) {
    const el = document.createElement('div');
    el.className = 'ad-shape'; el.dataset.id = p.id;
    const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
    svg.setAttribute('viewBox','0 0 100 100'); svg.setAttribute('preserveAspectRatio','none');
    const sh = document.createElementNS('http://www.w3.org/2000/svg', p.shape==='circle'?'ellipse':(p.shape==='rect'?'rect':'polygon'));
    sh.dataset.fig = '1';
    svg.appendChild(sh);
    el.appendChild(svg);
    const num = document.createElement('div'); num.className='ad-num'; el.appendChild(num);
    this.shapeEls[p.id] = el;
    this.styleShape(el, p);
    this.placeShape(el, p);
    return el;
  }
  placeShape(el, p) {
    el.style.left = ((p.x - p.w/2)*100)+'%';
    el.style.top = ((p.y - p.h/2)*100)+'%';
    el.style.width = (p.w*100)+'%';
    el.style.height = (p.h*100)+'%';
  }
  styleShape(el, p) {
    const col = this.statusRaw(p.status);
    const fig = el.querySelector('[data-fig]');
    const sel = p.id === this.selId;
    if (p.shape === 'rect') {
      fig.setAttribute('x','2'); fig.setAttribute('y','2');
      fig.setAttribute('width','96'); fig.setAttribute('height','96');
      const rx = Math.max(0,(p.radius||0));
      fig.setAttribute('rx', rx); fig.setAttribute('ry', rx);
    } else if (p.shape === 'circle') {
      fig.setAttribute('cx','50'); fig.setAttribute('cy','50');
      fig.setAttribute('rx','48'); fig.setAttribute('ry','48');
    } else {
      fig.setAttribute('points', p.points || '0,20 100,0 100,80 0,100');
    }
    fig.style.fill = sel ? this.hexA(col,0.34) : this.hexA(col,0.20);
    fig.style.stroke = col;
    fig.style.strokeWidth = sel ? '3' : '2.4';
    fig.setAttribute('vector-effect','non-scaling-stroke');
    fig.style.filter = `drop-shadow(0 0 5px ${this.hexA(col,0.6)})`;
    fig.style.transition = 'fill .15s ease';
    const num = el.querySelector('.ad-num'); num.textContent = p.label || '';
    num.style.color = '#fff';
    el.style.outline = sel ? '0' : '0';
    el.style.zIndex = sel ? '4' : '2';
  }
  hexA(hex,a){ const n=parseInt(hex.slice(1),16); return `rgba(${(n>>16)&255},${(n>>8)&255},${n&255},${a})`; }

  // ---------- list ----------
  renderList() {
    this.list.innerHTML = '';
    this.count.textContent = this.data.length;
    this.data.forEach(p => {
      const b = document.createElement('button');
      b.className = 'ad-li'; b.dataset.id = p.id;
      b.dataset.sel = p.id === this.selId ? '1' : '0';
      const dot = `<span style="width:9px;height:9px;border-radius:50%;flex-shrink:0;background:${this.statusColor(p.status)==='var(--neon)'?'#F5007D':this.statusRaw(p.status)};box-shadow:0 0 7px ${this.statusRaw(p.status)};"></span>`;
      const shp = ({rect:'▢',circle:'◯',poly:'◈'})[p.shape] || '▢';
      b.innerHTML = `${dot}<span style="flex:1;font-weight:700;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${p.label||'—'}</span><span style="color:var(--washi-faint);font-size:13px;">${shp}</span>`;
      b.addEventListener('click', () => this.select(p.id));
      this.list.appendChild(b);
    });
  }

  // ---------- selection ----------
  get selNote(){ return this.notes ? (this.notes.find(n => n.id === this.selNoteId) || null) : null; }
  get selHot(){ return this.hots ? (this.hots.find(h => h.id === this.selHotId) || null) : null; }

  // Показывает нужную панель: позиция / заметка / зона / пустой стейт.
  showPanel() {
    const p = this.sel, n = this.selNote, h = this.selHot;
    if (this.editor) this.editor.style.display = p ? 'flex' : 'none';
    if (this.noteEditor) this.noteEditor.style.display = (!p && n) ? 'flex' : 'none';
    if (this.hotEditor) this.hotEditor.style.display = (!p && !n && h) ? 'flex' : 'none';
    if (this.empty) this.empty.style.display = (p || n || h) ? 'none' : 'block';
  }

  select(id) {
    if (id && this.view !== 'b') this.setView('b');   // позиции редактируются на виде B
    this.selId = id;
    this.selNoteId = null;
    this.selHotId = null;
    this.list.querySelectorAll('.ad-li').forEach(b => b.dataset.sel = b.dataset.id===id ? '1':'0');
    this.data.forEach(p => { if (this.shapeEls[p.id]) this.styleShape(this.shapeEls[p.id], p); });
    this.styleNotes(); this.renderNoteListSel();
    this.styleHots(); this.renderHotListSel();
    const p = this.sel;
    this.removeHandles();
    if (p) { this.populate(p); this.buildHandles(p); }
    this.showPanel();
  }

  selectNote(id) {
    this.selNoteId = id;
    this.selId = null;
    this.selHotId = null;
    this.list.querySelectorAll('.ad-li').forEach(b => b.dataset.sel = '0');
    this.data.forEach(p => { if (this.shapeEls[p.id]) this.styleShape(this.shapeEls[p.id], p); });
    this.removeHandles();
    this.styleNotes(); this.renderNoteListSel();
    this.styleHots(); this.renderHotListSel();
    const n = this.selNote;
    if (n) this.populateNote(n);
    this.showPanel();
  }

  selectHot(id) {
    this.selHotId = id;
    this.selId = null;
    this.selNoteId = null;
    this.list.querySelectorAll('.ad-li').forEach(b => b.dataset.sel = '0');
    this.data.forEach(p => { if (this.shapeEls[p.id]) this.styleShape(this.shapeEls[p.id], p); });
    this.removeHandles();
    this.styleNotes(); this.renderNoteListSel();
    this.styleHots(); this.renderHotListSel();
    const h = this.selHot;
    if (h) this.populateHot(h);
    this.showPanel();
  }

  populate(p) {
    this.fLabel.value = p.label || '';
    this.fDesc.value = p.desc || '';
    this.fPhone.value = p.phone || '';
    this.fW.value = (p.w*100).toFixed(1); this.fWnum.value = (p.w*100).toFixed(1); this.wVal.textContent = (p.w*100).toFixed(1)+'%';
    this.fH.value = (p.h*100).toFixed(1); this.fHnum.value = (p.h*100).toFixed(1); this.hVal.textContent = (p.h*100).toFixed(1)+'%';
    this.fRadius.value = p.radius||0; this.radiusVal.textContent = (p.radius||0)+'%';
    this.radiusWrap.style.display = p.shape==='rect' ? 'block' : 'none';
    this.root.querySelectorAll('[data-status]').forEach(b => b.dataset.on = b.dataset.status===p.status ? '1':'0');
    this.root.querySelectorAll('[data-shape]').forEach(b => b.dataset.on = b.dataset.shape===p.shape ? '1':'0');
    if (p.photo) { this.photoImg.src = p.photo; this.photoImg.style.display='block'; this.photoHint.style.display='none'; }
    else { this.photoImg.removeAttribute('src'); this.photoImg.style.display='none'; this.photoHint.style.display='block'; }
  }

  update(fn, opts) {
    const p = this.sel; if (!p) return;
    fn(p);
    const el = this.shapeEls[p.id];
    if (el) { this.styleShape(el, p); this.placeShape(el, p); }
    if (opts && opts.list) this.renderList();
    if (opts && opts.handles) { this.removeHandles(); this.buildHandles(p); } else this.updateHandles(p);
    this.save();
  }

  // ---------- add / delete ----------
  add() {
    const n = this.data.length + 1;
    const p = { id:'p'+Date.now(), label:'Кнопка '+n, status:'free', shape:'rect',
      x:0.5, y:0.5, w:0.1, h:0.07, radius:14, points:'0,20 100,0 100,80 0,100',
      desc:'', phone:'', photo:'' };
    this.data.push(p);
    this.layer.appendChild(this.buildShape(p));
    this.renderList();
    this.save('Добавлено');
    this.select(p.id);
  }
  remove() {
    const p = this.sel; if (!p) return;
    this.data = this.data.filter(x => x.id !== p.id);
    if (this.shapeEls[p.id]) { this.shapeEls[p.id].remove(); delete this.shapeEls[p.id]; }
    this.selId = null;
    this.renderList(); this.select(null); this.save('Удалено');
  }

  // ---------- static bindings ----------
  bindStatic() {
    this.root.querySelectorAll('[data-view]').forEach(b => b.addEventListener('click', () => this.setView(b.dataset.view)));
    this.root.querySelector('[data-act=add]').addEventListener('click', () => this.add());
    this.root.querySelector('[data-act=delete]').addEventListener('click', () => this.remove());
    this.root.querySelector('[data-act=save]').addEventListener('click', () => this.save('Сохранено ✓'));
    this.root.querySelectorAll('[data-status]').forEach(b => b.addEventListener('click', () => {
      this.update(p => p.status = b.dataset.status, { list:true });
      this.root.querySelectorAll('[data-status]').forEach(x => x.dataset.on = x===b ? '1':'0');
    }));
    this.root.querySelectorAll('[data-shape]').forEach(b => b.addEventListener('click', () => {
      this.update(p => { p.shape = b.dataset.shape; }, { list:true, handles:true });
      this.root.querySelectorAll('[data-shape]').forEach(x => x.dataset.on = x===b ? '1':'0');
      if (this.sel) this.radiusWrap.style.display = this.sel.shape==='rect' ? 'block':'none';
    }));
    this.fLabel.addEventListener('input', () => this.update(p => p.label = this.fLabel.value, { list:true }));
    this.fDesc.addEventListener('input', () => this.update(p => p.desc = this.fDesc.value));
    this.fPhone.addEventListener('input', () => this.update(p => p.phone = this.fPhone.value));
    const wireNum = (rng, num, val, key) => {
      const apply = src => {
        let v = parseFloat(src.value); if (isNaN(v)) return;
        v = Math.max(2, Math.min(40, v));
        rng.value = v; num.value = v.toFixed(1); val.textContent = v.toFixed(1)+'%';
        this.update(p => p[key] = v/100);
      };
      rng.addEventListener('input', () => apply(rng));
      num.addEventListener('input', () => apply(num));
    };
    wireNum(this.fW, this.fWnum, this.wVal, 'w');
    wireNum(this.fH, this.fHnum, this.hVal, 'h');
    this.fRadius.addEventListener('input', () => { this.radiusVal.textContent = this.fRadius.value+'%'; this.update(p => p.radius = +this.fRadius.value); });
    // photo
    const pick = () => this.photoInput.click();
    this.root.querySelector('[data-act=photo-pick]').addEventListener('click', pick);
    this.photoWrap.addEventListener('click', pick);
    this.root.querySelector('[data-act=photo-clear]').addEventListener('click', () => {
      this.update(p => p.photo = '');
      this.photoImg.removeAttribute('src'); this.photoImg.style.display='none'; this.photoHint.style.display='block';
    });
    this.photoInput.addEventListener('change', e => this.readPhoto(e.target.files[0]));
    this.photoWrap.addEventListener('dragover', e => { e.preventDefault(); this.photoWrap.style.borderColor='var(--neon)'; });
    this.photoWrap.addEventListener('dragleave', () => { this.photoWrap.style.borderColor='var(--sumi-400)'; });
    this.photoWrap.addEventListener('drop', e => { e.preventDefault(); this.photoWrap.style.borderColor='var(--sumi-400)'; if (e.dataTransfer.files[0]) this.readPhoto(e.dataTransfer.files[0]); });
    // export / import
    this.root.querySelector('[data-act=export]').addEventListener('click', () => this.exportJSON());
    this.root.querySelector('[data-act=import]').addEventListener('click', () => this.importInput.click());
    this.importInput.addEventListener('change', e => this.importJSON(e.target.files[0]));
    // ----- заметки -----
    this.root.querySelector('[data-act=note-add]').addEventListener('click', () => this.addNote());
    this.root.querySelector('[data-act=note-delete]').addEventListener('click', () => this.removeNote());
    this.nText.addEventListener('input', () => { const n = this.selNote; if (!n) return; n.text = this.nText.value; this.renderNoteList(); this.saveNotes(); });
    const npick = () => this.nPhotoInput.click();
    this.root.querySelector('[data-act=note-photo-pick]').addEventListener('click', npick);
    this.nPhotoWrap.addEventListener('click', npick);
    this.root.querySelector('[data-act=note-photo-clear]').addEventListener('click', () => {
      const n = this.selNote; if (!n) return; n.photo = '';
      this.nPhotoImg.removeAttribute('src'); this.nPhotoImg.style.display='none'; this.nPhotoHint.style.display='block';
      this.renderNoteList(); this.saveNotes();
    });
    this.nPhotoInput.addEventListener('change', e => this.readNotePhoto(e.target.files[0]));
    this.nPhotoWrap.addEventListener('dragover', e => { e.preventDefault(); this.nPhotoWrap.style.borderColor='var(--neon)'; });
    this.nPhotoWrap.addEventListener('dragleave', () => { this.nPhotoWrap.style.borderColor='var(--sumi-400)'; });
    this.nPhotoWrap.addEventListener('drop', e => { e.preventDefault(); this.nPhotoWrap.style.borderColor='var(--sumi-400)'; if (e.dataTransfer.files[0]) this.readNotePhoto(e.dataTransfer.files[0]); });
    // ----- зоны-кнопки -----
    this.root.querySelector('[data-act=hot-add]').addEventListener('click', () => this.addHot());
    this.root.querySelector('[data-act=hot-delete]').addEventListener('click', () => this.removeHot());
    this.hLabel.addEventListener('input', () => this.updateHot(h => h.label = this.hLabel.value, { list:true }));
    this.hTitle.addEventListener('input', () => this.updateHot(h => h.title = this.hTitle.value));
    this.hDesc.addEventListener('input', () => this.updateHot(h => h.desc = this.hDesc.value));
    this.hPhone.addEventListener('input', () => this.updateHot(h => h.phone = this.hPhone.value));
    this.hFade.addEventListener('change', () => this.updateHot(h => h.hideOnTables = this.hFade.checked));
    this.root.querySelectorAll('[data-hkind]').forEach(b => b.addEventListener('click', () => {
      this.updateHot(h => h.kind = b.dataset.hkind, { list:true });
      this.root.querySelectorAll('[data-hkind]').forEach(x => x.dataset.on = x===b ? '1':'0');
      if (this.hPhoneWrap) this.hPhoneWrap.style.display = b.dataset.hkind==='phone' ? 'block':'none';
    }));
    this.root.querySelectorAll('[data-haccent]').forEach(b => b.addEventListener('click', () => {
      this.updateHot(h => h.accent = b.dataset.haccent, { list:true });
      this.root.querySelectorAll('[data-haccent]').forEach(x => x.dataset.on = x===b ? '1':'0');
    }));
    this.root.querySelectorAll('[data-hpref]').forEach(b => b.addEventListener('click', () => {
      this.updateHot(h => h.pref = +b.dataset.hpref);
      this.root.querySelectorAll('[data-hpref]').forEach(x => x.dataset.on = x===b ? '1':'0');
    }));
  }

  readPhoto(file) {
    if (!file || !this.sel) return;
    // Грузим на сервер (наш FileUploader) — в БД хранится путь, не base64.
    const fd = new FormData();
    fd.append('photo', file);
    fd.append('_csrf', window.__CSRF || '');
    fetch('/admin/booking/hall/photo', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(j => {
        if (!j || !j.ok) { this.flashSave(false, null, 'Ошибка фото'); return; }
        this.update(p => p.photo = j.path);
        this.photoImg.src = j.path; this.photoImg.style.display = 'block'; this.photoHint.style.display = 'none';
        this.save('Фото добавлено');
      })
      .catch(e => this.flashSave(false, e, 'Ошибка фото'));
  }

  exportJSON() {
    const blob = new Blob([JSON.stringify({ positions:this.data }, null, 2)], { type:'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = 'mirai-positions.json';
    a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    this.save('Экспортировано');
  }
  importJSON(file) {
    if (!file) return;
    const rd = new FileReader();
    rd.onload = () => {
      try {
        const o = JSON.parse(rd.result);
        if (o && Array.isArray(o.positions)) {
          this.data = o.positions; this.selId = null;
          this.renderLayer(); this.renderNotes(); this.renderHots(); this.renderList(); this.select(null); this.save('Импортировано');
        }
      } catch(e) { alert('Не удалось прочитать файл'); }
    };
    rd.readAsText(file);
    this.importInput.value = '';
  }

  // ---------- handles ----------
  buildHandles(p) {
    this._handles = [];
    const el = this.shapeEls[p.id]; if (!el) return;
    if (p.shape === 'poly') {
      const pts = this.parsePts(p.points);
      pts.forEach((pt, i) => {
        const h = document.createElement('div'); h.className='ad-handle'; h.dataset.vtx = i;
        el.appendChild(h); this._handles.push(h);
      });
    } else {
      const h = document.createElement('div'); h.className='ad-handle'; h.dataset.resize='1';
      h.style.left='100%'; h.style.top='100%'; el.appendChild(h); this._handles.push(h);
    }
    this.updateHandles(p);
  }
  removeHandles() { if (this._handles) { this._handles.forEach(h => h.remove()); this._handles=null; } }
  updateHandles(p) {
    if (!this._handles || !p) return;
    const el = this.shapeEls[p.id]; if (!el) return;
    if (p.shape === 'poly') {
      const pts = this.parsePts(p.points);
      el.querySelectorAll('[data-vtx]').forEach(h => { const pt = pts[+h.dataset.vtx]; if (pt) { h.style.left = pt[0]+'%'; h.style.top = pt[1]+'%'; } });
    }
  }
  parsePts(s){ return (s||'').trim().split(/\s+/).map(pr => pr.split(',').map(Number)); }

  // ---------- pointer ----------
  initPointer() {
    const st = this.stage; const self = this;
    st.addEventListener('pointerdown', e => {
      const hotEl = e.target.closest('.ad-hot');
      if (hotEl) {
        e.preventDefault();
        try{ st.setPointerCapture(e.pointerId);}catch(_){}
        const id = hotEl.dataset.hot;
        if (id !== this.selHotId) this.selectHot(id);
        const h = this.selHot;
        const c = h[this.view] || h.a || [0.5,0.5];
        this._drag = { type:'hotmove', h, view:this.view, sx:e.clientX, sy:e.clientY, x:c[0], y:c[1] };
        return;
      }
      const noteEl = e.target.closest('.ad-note');
      if (noteEl) {
        e.preventDefault();
        try{ st.setPointerCapture(e.pointerId);}catch(_){}
        const id = noteEl.dataset.note;
        if (id !== this.selNoteId) this.selectNote(id);
        const n = this.selNote;
        this._drag = { type:'notemove', n, sx:e.clientX, sy:e.clientY, x:n.x, y:n.y };
        return;
      }
      const handle = e.target.closest('.ad-handle');
      const shapeEl = e.target.closest('.ad-shape');
      if (handle && this.sel) {
        e.preventDefault();
        try{ st.setPointerCapture(e.pointerId);}catch(_){}
        const p = this.sel;
        if (handle.dataset.resize) {
          this._drag = { type:'resize', p, sx:e.clientX, sy:e.clientY, w:p.w, h:p.h };
        } else {
          this._drag = { type:'vtx', p, idx:+handle.dataset.vtx, pts:this.parsePts(p.points), sx:e.clientX, sy:e.clientY };
        }
        return;
      }
      if (shapeEl) {
        e.preventDefault();
        try{ st.setPointerCapture(e.pointerId);}catch(_){}
        const id = shapeEl.dataset.id;
        if (id !== this.selId) this.select(id);
        const p = this.sel;
        this._drag = { type:'move', p, sx:e.clientX, sy:e.clientY, x:p.x, y:p.y, moved:false };
        return;
      }
    });
    st.addEventListener('pointermove', e => {
      const d = this._drag; if (!d) return;
      const dx = (e.clientX - d.sx)/this.baseW, dy = (e.clientY - d.sy)/this.baseH;
      if (d.type === 'hotmove') {
        const nx = Math.max(0.02, Math.min(0.98, d.x + dx));
        const ny = Math.max(0.02, Math.min(0.98, d.y + dy));
        d.h[d.view] = [nx, ny];
        const el = (this.hotEls||[]).find(x => x.dataset.hot === d.h.id);
        if (el) this.placeHotPin(el, d.h);
        return;
      }
      if (d.type === 'notemove') {
        d.n.x = Math.max(0.02, Math.min(0.98, d.x + dx));
        d.n.y = Math.max(0.02, Math.min(0.98, d.y + dy));
        const el = (this.noteEls||[]).find(x => x.dataset.note === d.n.id);
        if (el) this.placeNotePin(el, d.n);
        return;
      }
      if (d.type === 'move') {
        d.p.x = Math.max(0, Math.min(1, d.x + dx));
        d.p.y = Math.max(0, Math.min(1, d.y + dy));
        if (Math.abs(e.clientX-d.sx)+Math.abs(e.clientY-d.sy)>3) d.moved=true;
        this.placeShape(this.shapeEls[d.p.id], d.p);
      } else if (d.type === 'resize') {
        d.p.w = Math.max(0.02, Math.min(0.5, d.w + dx));
        d.p.h = Math.max(0.02, Math.min(0.5, d.h + dy));
        this.placeShape(this.shapeEls[d.p.id], d.p);
        this.fW.value=(d.p.w*100).toFixed(1); this.fWnum.value=(d.p.w*100).toFixed(1); this.wVal.textContent=(d.p.w*100).toFixed(1)+'%';
        this.fH.value=(d.p.h*100).toFixed(1); this.fHnum.value=(d.p.h*100).toFixed(1); this.hVal.textContent=(d.p.h*100).toFixed(1)+'%';
      } else if (d.type === 'vtx') {
        const boxW = d.p.w*this.baseW, boxH = d.p.h*this.baseH;
        const pts = d.pts.map(pt => pt.slice());
        pts[d.idx][0] = Math.max(-10, Math.min(110, d.pts[d.idx][0] + (e.clientX-d.sx)/boxW*100));
        pts[d.idx][1] = Math.max(-10, Math.min(110, d.pts[d.idx][1] + (e.clientY-d.sy)/boxH*100));
        d.p.points = pts.map(pt => pt[0].toFixed(1)+','+pt[1].toFixed(1)).join(' ');
        const fig = this.shapeEls[d.p.id].querySelector('[data-fig]'); if (fig) fig.setAttribute('points', d.p.points);
        this.updateHandles(d.p);
      }
    });
    const up = e => {
      if (this._drag) { if (this._drag.type === 'notemove') this.saveNotes(); else this.save(); this._drag = null; }
    };
    st.addEventListener('pointerup', up);
    st.addEventListener('pointercancel', up);
    st.addEventListener('wheel', e => {
      const shapeEl = e.target.closest('.ad-shape');
      if (!shapeEl) return;
      e.preventDefault();
      const id = shapeEl.dataset.id;
      if (id !== this.selId) this.select(id);
      const p = this.sel; if (!p) return;
      const f = e.deltaY < 0 ? 1.06 : 1/1.06;
      p.w = Math.max(0.02, Math.min(0.5, p.w*f));
      p.h = Math.max(0.02, Math.min(0.5, p.h*f));
      this.placeShape(this.shapeEls[p.id], p);
      this.fW.value=(p.w*100).toFixed(1); this.fWnum.value=(p.w*100).toFixed(1); this.wVal.textContent=(p.w*100).toFixed(1)+'%';
      this.fH.value=(p.h*100).toFixed(1); this.fHnum.value=(p.h*100).toFixed(1); this.hVal.textContent=(p.h*100).toFixed(1)+'%';
      clearTimeout(this._wst); this._wst = setTimeout(() => this.save(), 200);
    }, { passive:false });
    // click empty stage deselects
    st.addEventListener('click', e => {
      if (!e.target.closest('.ad-shape') && !e.target.closest('.ad-handle') && !(this._lastMoved)) {
        // keep selection; do nothing to avoid accidental deselect
      }
    });
  }
}

var rootEl = document.querySelector('[data-ref="setRoot"]');
window.__ed = new Component(rootEl);
