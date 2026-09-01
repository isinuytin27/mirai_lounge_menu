/* ── Лёгкая обёртка вместо прототип-рантайма support.js / _ds ──
   Воспроизводит привязки ref="{{ setter }}" и lifecycle componentDidMount. */
class DCLogic {
  constructor(root){
    this.root = root;
    var vals = this.renderVals ? this.renderVals() : {};
    if (root.dataset && root.dataset.ref && vals[root.dataset.ref]) vals[root.dataset.ref](root);
    root.querySelectorAll('[data-ref]').forEach(function(el){
      var f = vals[el.dataset.ref]; if (f) f(el);
    });
    if (this.componentDidMount) this.componentDidMount();
  }
}


class Component extends DCLogic {
  renderVals() {
    return {
      setRoot: el => { this.root = el; },
      setStage: el => { this.stage = el; },
      setWorld: el => { this.world = el; },
      setImgA: el => { this.imgA = el; },
      setImgB: el => { this.imgB = el; },
      setFrames: el => { this.frames = el; },
      setHandle: el => { this.handle = el; },
      setTrack: el => { this.track = el; },
      setTrackFill: el => { this.trackFill = el; },
      setViewLabel: el => { this.viewLabel = el; },
      setHint: el => { this.hint = el; },
    };
  }

  componentDidMount() {
    this.AR = 1672 / 941;
    this.v = { t: 0, scale: 1, tx: 0, ty: 0 };
    this.pops = {};
    // статические попапы (стол, заметка); хотспоты зон строятся динамически из конфига
    this.root.querySelectorAll('[data-pop]').forEach(el => { this.pops[el.dataset.pop] = el; });
    // Попапы не должны отдавать pointer-события сцене — иначе нажатие на «Забронировать»
    // сцена принимает за тап по карте и закрывает попап раньше, чем сработает клик.
    this.root.querySelectorAll('.mk-pop').forEach(el => { el.addEventListener('pointerdown', e => e.stopPropagation()); });
    this.buildHotspots();   // this.hs / this.hsEls / this.hsByKey + добавляет попапы зон в this.pops
    this.openPopKey = null;
    this.tablePop = this.root.querySelector('[data-pop=table]');
    this.buildTables();
    this.maskEls = [...this.world.querySelectorAll('.mk-mask')];
    this.tableEls.forEach(el => { this.layoutTable(el); el.style.opacity = '0'; });
    // ── доступность по выбранному дню: подсветка занятых столов ──
    this.bkDate = this.todayISO();
    const di = this.root.querySelector('[data-act=mapdate]');
    if (di) {
      di.value = this.bkDate;
      di.addEventListener('change', e => {
        this.bkDate = e.target.value; this.closePops();
        // Занятость на новую дату тянем с сервера (mirai-sync перекрасит столы).
        if (window.MiraiStore && MiraiStore.pullOccupancy) MiraiStore.pullOccupancy(this.bkDate);
        this.applyBookingStatuses();
      });
      di.addEventListener('pointerdown', e => e.stopPropagation());
    }
    this.applyBookingStatuses();
    this._onStorage = e => {
      if (e.key === 'mirai_admin_v1') { this.refreshFromAdmin(); this.applyBookingStatuses(); }
      else if (e.key === 'mirai_bookings_v1') this.applyBookingStatuses();
      else if (e.key === 'mirai_notes_v1') { this.loadNotes(); this.buildNotes(); }
    };
    window.addEventListener('storage', this._onStorage);
    this.editMode = false;
    this.editHint = this.root.querySelector('[data-edit-hint]');
    const eb = this.root.querySelector('[data-act=edit]');
    if (eb) eb.addEventListener('click', () => this.toggleEdit());

    // Заметки на карте — только для просмотра (создаются/редактируются в editor.html).
    this.isAdmin = false;
    this.noteMode = false;
    this.noteHint = this.root.querySelector('[data-note-hint]');
    this.notePop = this.root.querySelector('[data-pop=note]');
    const ntb = this.root.querySelector('[data-act=note]');
    if (ntb) ntb.addEventListener('click', () => this.toggleNote());
    this.loadNotes();
    this.buildNotes();
    this.bindNotePop();

    this.root.querySelectorAll('[data-zone]').forEach(el => {
      el.addEventListener('click', e => { e.stopPropagation(); this.goToZone(el.dataset.zone); });
    });
    // делегирование — закрывает попапы, включая динамически построенные хотспоты зон
    this.root.addEventListener('click', e => {
      const c = e.target.closest && e.target.closest('[data-act=close]');
      if (c) { e.stopPropagation(); this.closePops(); }
    });
    this.root.querySelector('[data-act=zin]').addEventListener('click', () => this.zoomCenter(1.4));
    this.root.querySelector('[data-act=zout]').addEventListener('click', () => this.zoomCenter(1 / 1.4));
    this.root.querySelector('[data-act=reset]').addEventListener('click', () => this.reset());
    this.root.querySelectorAll('[data-view-end]').forEach(el => {
      el.addEventListener('click', () => this.animate({ t: el.dataset.viewEnd === 'b' ? 1 : 0, scale: 1 }, 600));
    });

    this.initPointer();
    this.initTrack();
    this.stage.addEventListener('wheel', e => {
      if (this.editMode) {
        const tb = e.target.closest('[data-table]');
        if (tb) {
          e.preventDefault();
          const f = e.deltaY < 0 ? 1.06 : 1 / 1.06;
          tb.dataset.w = Math.max(0.03, Math.min(0.5, +tb.dataset.w * f)).toFixed(4);
          tb.dataset.h = Math.max(0.02, Math.min(0.5, +tb.dataset.h * f)).toFixed(4);
          this.layoutTable(tb);
          this.saveTables();
          return;
        }
      }
      e.preventDefault(); this.hideHint();
      this.zoomAt(e.clientX, e.clientY, this.v.scale * (e.deltaY < 0 ? 1.12 : 1 / 1.12));
    }, { passive: false });
    this.stage.addEventListener('dblclick', e => {
      this.hideHint();
      if (this.v.scale > 1.05) this.reset(); else this.zoomAt(e.clientX, e.clientY, 2.4);
    });
    this._onResize = () => this.layout();
    window.addEventListener('resize', this._onResize);
    this.layout();
    this.initFrames();   // если в assets/frames/ есть кадры турнтейбла — используем их вместо вида A/B
  }

  componentWillUnmount() { window.removeEventListener('resize', this._onResize); }

  /* ── Турнтейбл из кадров: assets/frames/frame-001.png, frame-002.png, …
     Кадр 1 = вид A, последний кадр = вид B (та же дуга, что и слайдер).
     Кадры определяются автоматически (последовательная проба до первого пропуска). ── */
  initFrames() {
    this.frameEls = [];
    this.useFrames = false;
    const dir = '/assets/booking/frames/';
    const pad = n => String(n).padStart(3, '0');
    const MAX = 240;
    const load = i => {
      if (i > MAX) return this.finishFrames();
      const probe = new Image();
      probe.onload = () => {
        const el = document.createElement('img');
        el.src = probe.src; el.draggable = false; el.alt = '';
        el.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:contain;opacity:0;-webkit-user-drag:none;pointer-events:none;';
        this.frames.appendChild(el);
        this.frameEls.push(el);
        load(i + 1);
      };
      probe.onerror = () => this.finishFrames();
      probe.src = dir + 'frame-' + pad(i) + '.png';
    };
    load(1);
  }
  finishFrames() {
    if (this.frameEls.length >= 2) {
      this.useFrames = true;
      if (this.imgA) this.imgA.style.display = 'none';
      if (this.imgB) this.imgB.style.display = 'none';
      this.apply();
    } else {
      this.frameEls.forEach(el => el.remove());
      this.frameEls = [];
    }
  }

  layout() {
    const r = this.stage.getBoundingClientRect();
    const padT = Math.min(150, r.height * 0.17);
    const padB = Math.min(168, r.height * 0.2);
    const padX = Math.min(80, r.width * 0.06);
    const availW = r.width - padX * 2;
    const availH = r.height - padT - padB;
    let w = availW, h = w / this.AR;
    if (h > availH) { h = availH; w = h * this.AR; }
    this.baseW = w; this.baseH = h;
    this.world.style.width = w + 'px';
    this.world.style.height = h + 'px';
    this.world.style.left = (r.width - w) / 2 + 'px';
    this.world.style.top = (padT + (availH - h) / 2) + 'px';
    this.apply();
  }

  apply() {
    const v = this.v;
    const dip = 1 - 0.05 * Math.sin(Math.PI * v.t);
    const blur = 1.6 * Math.sin(Math.PI * v.t);
    this.world.style.transform = `translate(${v.tx}px,${v.ty}px) scale(${(v.scale * dip).toFixed(4)})`;
    this.world.style.filter = blur > 0.05 ? `blur(${blur.toFixed(2)}px)` : 'none';
    this.world.style.setProperty('--inv', (1 / (v.scale * dip)).toFixed(4));
    if (this.useFrames && this.frameEls && this.frameEls.length) {
      // турнтейбл: кадр по позиции вращения t, мягкий кросс-фейд между соседними
      const N = this.frameEls.length;
      const fp = Math.max(0, Math.min(N - 1, v.t * (N - 1)));
      const i0 = Math.floor(fp), i1 = Math.min(N - 1, i0 + 1), fr = fp - i0;
      for (let i = 0; i < N; i++) this.frameEls[i].style.opacity = (i === i0 ? (1 - fr) : (i === i1 ? fr : 0)).toFixed(3);
    } else {
      const s = this.smooth(v.t);
      this.imgA.style.opacity = (1 - s).toFixed(3);
      this.imgB.style.opacity = s.toFixed(3);
    }
    for (const k in this.hsEls) {
      const d = this.hs[k];
      const x = d.a[0] + (d.b[0] - d.a[0]) * v.t;
      const y = d.a[1] + (d.b[1] - d.a[1]) * v.t;
      this.hsEls[k].style.left = (x * 100) + '%';
      this.hsEls[k].style.top = (y * 100) + '%';
    }
    const tv0 = Math.max(0, Math.min(1, (v.t - 0.62) / 0.33));
    const tvs = tv0 * tv0 * (3 - 2 * tv0);
    if (this.tableEls) for (const el of this.tableEls) {
      el.style.opacity = tvs.toFixed(3);
      const n = el.firstElementChild; if (n) n.style.pointerEvents = tvs > 0.5 ? 'auto' : 'none';
    }
    for (const k in this.hsEls) {
      if (this.hsByKey && this.hsByKey[k] && this.hsByKey[k].hideOnTables) this.hsEls[k].style.opacity = (1 - tvs).toFixed(3);
    }
    if (this.maskEls) for (const m of this.maskEls) m.style.opacity = tvs.toFixed(3);
    this.updateNoteVis();
    if (this.openPopKey === 'table' && v.t < 0.8) this.closePops();
    if (this.handle) this.handle.style.left = (v.t * 100) + '%';
    if (this.trackFill) this.trackFill.style.width = (v.t * 100) + '%';
    if (this.viewLabel) this.viewLabel.textContent = v.t < 0.04 ? 'Вид A' : (v.t > 0.96 ? 'Вид B' : 'Поворот');
  }

  smooth(t) { const x = Math.max(0, Math.min(1, (t - 0.32) / 0.36)); return x * x * (3 - 2 * x); }

  clampPan() {
    const mx = Math.max(0, (this.baseW * (this.v.scale - 1)) / 2 + this.baseW * 0.12);
    const my = Math.max(0, (this.baseH * (this.v.scale - 1)) / 2 + this.baseH * 0.12);
    this.v.tx = Math.max(-mx, Math.min(mx, this.v.tx));
    this.v.ty = Math.max(-my, Math.min(my, this.v.ty));
  }

  zoomAt(clientX, clientY, ns) {
    const r = this.stage.getBoundingClientRect();
    const cx = clientX - r.left, cy = clientY - r.top;
    const ox = r.width / 2, oy = r.height / 2;
    ns = Math.max(1, Math.min(6, ns));
    const Lx = (cx - ox - this.v.tx) / this.v.scale;
    const Ly = (cy - oy - this.v.ty) / this.v.scale;
    this.v.scale = ns;
    this.v.tx = cx - ox - ns * Lx;
    this.v.ty = cy - oy - ns * Ly;
    if (ns <= 1.001) { this.v.tx = 0; this.v.ty = 0; }
    this.clampPan(); this.apply();
  }

  zoomCenter(f) {
    const r = this.stage.getBoundingClientRect();
    this.zoomAt(r.left + r.width / 2, r.top + r.height / 2, this.v.scale * f);
  }

  reset() { this.animate({ scale: 1, t: this.v.t }, 380); }

  initPointer() {
    const stage = this.stage; const pts = new Map();
    let mode = null, start = null;
    stage.addEventListener('pointerdown', e => {
      try { stage.setPointerCapture(e.pointerId); } catch (_) {}
      pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
      this.hideHint(); this._moved = false;
      const dot = e.target.closest('[data-dot]');
      let dk = dot ? dot.dataset.dot : null;
      if (dk && parseFloat(this.hsEls[dk].style.opacity || '1') < 0.5) dk = null;
      this._downDot = dk;
      const tb = e.target.closest('[data-table]');
      this._downTable = (tb && parseFloat(tb.style.opacity || '1') >= 0.5) ? tb : null;
      const hb = e.target.closest('[data-handle]');
      this._downHandle = (this.editMode && hb) ? hb : null;
      const ndn = e.target.closest('[data-note]');
      this._downNote = ndn || null;
      if (pts.size === 1) {
        if (this.editMode && this._downHandle) {
          mode = 'handle';
          const tel = this._downHandle.parentElement;
          this._hdrag = { el: tel, idx: +this._downHandle.dataset.handle, pts: this.parsePts(tel.dataset.points), sx: e.clientX, sy: e.clientY, w: +tel.dataset.w };
        } else if (this.editMode && this._downTable) {
          mode = 'tabledrag';
          this._tdrag = { el: this._downTable, x: +this._downTable.dataset.x, y: +this._downTable.dataset.y, sx: e.clientX, sy: e.clientY };
        } else if (this.noteMode && this._downNote) {
          mode = 'notedrag';
          this._ndrag = { el: this._downNote, x: +this._downNote.dataset.x, y: +this._downNote.dataset.y, sx: e.clientX, sy: e.clientY };
        } else {
          mode = (this.editMode || this.v.scale > 1.02) ? 'pan' : 'rotate';
          start = { x: e.clientX, y: e.clientY, t: this.v.t, tx: this.v.tx, ty: this.v.ty };
          stage.style.cursor = 'grabbing';
        }
      } else if (pts.size === 2) {
        mode = 'pinch'; const a = [...pts.values()];
        this.pinch = { d: Math.hypot(a[0].x - a[1].x, a[0].y - a[1].y), s: this.v.scale };
      }
    });
    stage.addEventListener('pointermove', e => {
      if (!pts.has(e.pointerId)) return;
      pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
      if (mode === 'handle' && this._hdrag) {
        const d = this._hdrag;
        const boxW = d.w * this.baseW * this.v.scale;
        const boxH = (+d.el.dataset.h) * this.baseH * this.v.scale;
        const pts = d.pts.map(p => p.slice());
        pts[d.idx][0] = d.pts[d.idx][0] + (e.clientX - d.sx) / boxW * 100;
        pts[d.idx][1] = d.pts[d.idx][1] + (e.clientY - d.sy) / boxH * 100;
        const s = pts.map(p => p[0].toFixed(2) + ',' + p[1].toFixed(2)).join(' ');
        d.el.dataset.points = s;
        const poly = d.el.querySelector('polygon'); if (poly) poly.setAttribute('points', s);
        this.updateHandles(d.el);
        this._moved = true;
        return;
      }
      if (mode === 'tabledrag' && this._tdrag) {
        const d = this._tdrag;
        d.el.dataset.x = (d.x + (e.clientX - d.sx) / (this.baseW * this.v.scale)).toFixed(4);
        d.el.dataset.y = (d.y + (e.clientY - d.sy) / (this.baseH * this.v.scale)).toFixed(4);
        this.layoutTable(d.el);
        this._moved = true;
        return;
      }
      if (mode === 'notedrag' && this._ndrag) {
        const d = this._ndrag;
        d.el.dataset.x = Math.max(0.02, Math.min(0.98, d.x + (e.clientX - d.sx) / (this.baseW * this.v.scale))).toFixed(4);
        d.el.dataset.y = Math.max(0.02, Math.min(0.98, d.y + (e.clientY - d.sy) / (this.baseH * this.v.scale))).toFixed(4);
        this.layoutNote(d.el);
        this._moved = true;
        return;
      }
      if (mode === 'pinch' && pts.size >= 2) {
        const a = [...pts.values()];
        const d = Math.hypot(a[0].x - a[1].x, a[0].y - a[1].y);
        this.zoomAt((a[0].x + a[1].x) / 2, (a[0].y + a[1].y) / 2, this.pinch.s * (d / this.pinch.d));
        this._moved = true; return;
      }
      if (!start) return;
      const dx = e.clientX - start.x, dy = e.clientY - start.y;
      if (Math.abs(dx) + Math.abs(dy) > 5) this._moved = true;
      if (mode === 'rotate') {
        this.v.t = Math.max(0, Math.min(1, start.t + dx / (stage.clientWidth * 0.7)));
      } else {
        this.v.tx = start.tx + dx; this.v.ty = start.ty + dy; this.clampPan();
      }
      this.apply();
    });
    const up = e => {
      if (pts.has(e.pointerId)) pts.delete(e.pointerId);
      if (pts.size < 2) this.pinch = null;
      if (pts.size === 0) {
        mode = null; start = null; stage.style.cursor = 'grab';
        if (this._hdrag) { this.saveTables(); this._hdrag = null; }
        else if (this._tdrag) { this.saveTables(); this._tdrag = null; }
        else if (this._ndrag) { this.saveNotes(); this._ndrag = null; }
        else if (!this._moved) {
          if (this._downNote) this.openNote(this._downNote, this.noteMode);
          else if (this.noteMode) this.addNoteAt(e.clientX, e.clientY);
          else if (this._downTable && !this.editMode) this.openTable(this._downTable);
          else if (this._downDot) this.goToZone(this._downDot);
          else this.closePops();
        }
      } else {
        const id = [...pts.keys()][0]; const p = pts.get(id);
        mode = this.v.scale > 1.02 ? 'pan' : 'rotate';
        start = { x: p.x, y: p.y, t: this.v.t, tx: this.v.tx, ty: this.v.ty };
      }
    };
    stage.addEventListener('pointerup', up);
    stage.addEventListener('pointercancel', up);
  }

  initTrack() {
    const tr = this.track; let drag = false;
    const set = e => {
      const r = tr.getBoundingClientRect();
      this.v.t = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
      this.apply();
    };
    tr.addEventListener('pointerdown', e => { drag = true; tr.setPointerCapture(e.pointerId); this.hideHint(); set(e); });
    tr.addEventListener('pointermove', e => { if (drag) set(e); });
    tr.addEventListener('pointerup', () => { drag = false; });
    tr.addEventListener('pointercancel', () => { drag = false; });
  }

  togglePop(k) {
    if (this.openPopKey === k) { this.closePops(); return; }
    this.closePops();
    const p = this.pops[k]; if (!p) return;
    const hs = this.hsEls[k];
    // попап — потомок хотспота; на виде B хотспот Lounge затухает (opacity 0),
    // поэтому пока попап открыт принудительно держим хотспот видимым.
    if (hs) hs.style.opacity = '1';
    p.dataset.open = '1';
    p.style.opacity = '1';
    p.style.pointerEvents = 'auto';
    const dot = hs.querySelector('[data-dot]');
    this.placePop(p, (dot || hs).getBoundingClientRect());
    this.openPopKey = k;
  }

  placePop(p, rect) {
    const dA = p.querySelector('[data-arrow=down]');
    const uA = p.querySelector('[data-arrow=up]');
    const ph = p.getBoundingClientRect().height || 170;
    const half = (rect.height || 30) / 2;
    const gap = 14;
    const roomAbove = rect.top - half - ph - 12 > 8;
    if (roomAbove) {
      p.style.transformOrigin = 'bottom center';
      p.style.transform = `translate(-50%,-100%) translateY(${-(half + gap)}px) scale(var(--inv,1))`;
      if (dA) dA.style.display = 'block';
      if (uA) uA.style.display = 'none';
    } else {
      p.style.transformOrigin = 'top center';
      p.style.transform = `translate(-50%,0) translateY(${half + gap}px) scale(var(--inv,1))`;
      if (dA) dA.style.display = 'none';
      if (uA) uA.style.display = 'block';
    }
    const host = p.closest('.mk-hs') || p;
    host.style.zIndex = '120';
  }

  closePops() {
    for (const k in this.pops) {
      this.pops[k].dataset.open = '0';
      this.pops[k].style.opacity = '0';
      this.pops[k].style.pointerEvents = 'none';
      this.pops[k].style.transform = 'translate(-50%,-100%) translateY(-6px) scale(var(--inv,1))';
      const host = this.pops[k].closest('.mk-hs') || this.pops[k];
      host.style.zIndex = '';
    }
    this.openPopKey = null;
    if (this.world) this.apply();   // вернуть затухание хотспотов (Lounge на виде B)
  }

  layoutTable(el) {
    const x = +el.dataset.x, y = +el.dataset.y, w = +el.dataset.w, h = +el.dataset.h;
    el.style.width = (w * 100) + '%';
    el.style.height = (h * 100) + '%';
    el.style.left = ((x - w / 2) * 100) + '%';
    el.style.top = ((y - h / 2) * 100) + '%';
  }

  parsePts(s) { return (s || '').trim().split(/\s+/).map(pr => pr.split(',').map(Number)); }

  createHandles() {
    this.removeHandles();
    this._handleEls = [];
    this.tableEls.forEach(el => {
      for (let i = 0; i < 4; i++) {
        const h = document.createElement('div');
        h.className = 'mk-handle'; h.dataset.handle = i;
        el.appendChild(h); this._handleEls.push(h);
      }
      this.updateHandles(el);
    });
  }

  removeHandles() { if (this._handleEls) { this._handleEls.forEach(h => h.remove()); this._handleEls = null; } }

  updateHandles(el) {
    const pts = this.parsePts(el.dataset.points);
    el.querySelectorAll('.mk-handle').forEach(h => {
      const p = pts[+h.dataset.handle];
      if (p) { h.style.left = p[0] + '%'; h.style.top = p[1] + '%'; }
    });
  }

  readAdmin() { try { return JSON.parse(localStorage.getItem('mirai_admin_v1')); } catch (e) { return null; } }

  seedPositions() {
    const T = [['11',.305,.315,.115,.0856],['12',.385,.270,.105,.0645],['13',.552,.385,.16,.0707],
      ['14',.300,.430,.105,.0781],['15',.300,.515,.105,.0781],['16',.298,.585,.10,.0744],
      ['17',.415,.415,.115,.0625],['18',.445,.485,.115,.0856]];
    return T.map(t => ({ id:'t'+t[0], label:'Стол '+t[0], status:'busy', shape:'poly',
      x:t[1], y:t[2], w:t[3], h:t[4], radius:14, points:'0,51 63,0 100,49 37,100',
      desc:'Lounge · бронирование стола заранее', phone:'+74242218080', photo:'', seats:4 }));
  }

  rgba(hex, a) { const n = parseInt(hex.slice(1), 16); return `rgba(${(n>>16)&255},${(n>>8)&255},${n&255},${a})`; }
  statusHex(s) { return s==='free'?'#3DDC84':s==='live'?'#2DE2E6':'#F5007D'; }

  buildTables() {
    this.world.querySelectorAll('[data-table]').forEach(el => el.remove());
    const cfg = this.readAdmin();
    const positions = (cfg && Array.isArray(cfg.positions) && cfg.positions.length) ? cfg.positions : this.seedPositions();
    positions.forEach(p => this.world.appendChild(this.makeTableEl(p)));
    this.tableEls = [...this.world.querySelectorAll('[data-table]')];
  }

  makeTableEl(p) {
    const NS = 'http://www.w3.org/2000/svg';
    const el = document.createElement('div');
    el.className = 'mk-table';
    el.dataset.id = p.id;
    el.dataset.label = p.label || '';
    el.dataset.seats = (p.seats != null ? p.seats : 4);
    el.dataset.table = (String(p.label || '').match(/\d+/) || [''])[0];
    el.dataset.x = p.x; el.dataset.y = p.y; el.dataset.w = p.w; el.dataset.h = (p.h != null ? p.h : 0.07);
    el.dataset.shape = p.shape || 'poly';
    el.dataset.radius = (p.radius != null ? p.radius : 14);
    el.dataset.points = p.points || '0,51 63,0 100,49 37,100';
    el.dataset.status = p.status || 'busy';
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 100 100');
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.setAttribute('style', 'position:absolute;inset:0;width:100%;height:100%;overflow:visible;');
    const tag = el.dataset.shape === 'circle' ? 'ellipse' : (el.dataset.shape === 'rect' ? 'rect' : 'polygon');
    const fig = document.createElementNS(NS, tag);
    fig.setAttribute('data-fig', '1');
    fig.setAttribute('class', 'mk-tshape');
    svg.appendChild(fig);
    el.appendChild(svg);
    const num = document.createElement('div'); num.className = 'mk-tnum';
    el.appendChild(num);
    this.styleTable(el);
    return el;
  }

  styleTable(el) {
    const shape = el.dataset.shape || 'poly';
    const fig = el.querySelector('[data-fig]');
    if (!fig) return;
    if (shape === 'rect') {
      const rx = Math.max(0, +el.dataset.radius || 0);
      fig.setAttribute('x','2'); fig.setAttribute('y','2');
      fig.setAttribute('width','96'); fig.setAttribute('height','96');
      fig.setAttribute('rx', rx); fig.setAttribute('ry', rx);
    } else if (shape === 'circle') {
      fig.setAttribute('cx','50'); fig.setAttribute('cy','50');
      fig.setAttribute('rx','48'); fig.setAttribute('ry','48');
    } else {
      fig.setAttribute('points', el.dataset.points || '0,51 63,0 100,49 37,100');
    }
    const col = this.statusHex(el.dataset.status || 'busy');
    fig.style.setProperty('--tfill', this.rgba(col, 0.20));
    fig.style.setProperty('--tfill-h', this.rgba(col, 0.46));
    fig.style.setProperty('--tstroke', col);
    fig.style.setProperty('--tglow', `drop-shadow(0 0 5px ${this.rgba(col,0.55)})`);
    const num = el.querySelector('.mk-tnum');
    if (num) num.textContent = el.dataset.table || el.dataset.label || '';
  }

  refreshFromAdmin() {
    this.closePops();
    this.buildTables();
    this.tableEls.forEach(el => this.layoutTable(el));
    this.buildHotspots();
    this.applyBookingStatuses();
    this.apply();
  }

  /* ── Хотспоты зон (Lounge, ПК-зона, …) — строятся из mirai_admin_v1.hotspots ── */
  esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c])); }
  seedHotspots() {
    return [
      { id:'lounge', label:'Lounge', kind:'zone', accent:'neon', title:'Зона со столами',
        desc:'Столы и места для отдыха. Можно забронировать заранее.', phone:'',
        a:[0.515,0.498], b:[0.418,0.452], pref:0, hideOnTables:true },
      { id:'pc', label:'ПК-зона', kind:'phone', accent:'cyan', title:'Игровые станции',
        desc:'Бронирование по телефону', phone:'+7 4242 21-80-80',
        a:[0.338,0.342], b:[0.717,0.560], pref:0, hideOnTables:false }
    ];
  }
  buildHotspots() {
    if (this.hsEls) Object.keys(this.hsEls).forEach(k => { if (this.hsEls[k]) this.hsEls[k].remove(); if (this.pops) delete this.pops[k]; });
    this.hs = {}; this.hsEls = {}; this.hsByKey = {};
    const cfg = this.readAdmin();
    const list = (cfg && Array.isArray(cfg.hotspots) && cfg.hotspots.length) ? cfg.hotspots : this.seedHotspots();
    list.forEach(h => {
      if (!h || !h.id) return;
      const a = h.a || [0.5, 0.5];
      this.hs[h.id] = { a: a, b: h.b || a, pref: (h.pref != null ? h.pref : 0) };
      this.hsByKey[h.id] = h;
      const el = this.makeHotspotEl(h);
      this.world.appendChild(el);
      this.hsEls[h.id] = el;
      const pop = el.querySelector('.mk-pop');
      this.pops[h.id] = pop;
      pop.addEventListener('pointerdown', e => e.stopPropagation());
    });
  }
  makeHotspotEl(h) {
    const cyan = h.accent === 'cyan';
    const accent = cyan ? 'var(--cyan)' : 'var(--neon)';
    const glow = cyan ? 'var(--glow-cyan)' : 'var(--glow-neon)';
    const line = cyan ? 'rgba(45,226,230,.4)' : 'var(--neon-line)';
    const badge = cyan ? 'badge badge--live' : 'badge badge--busy';
    const delay = cyan ? ' .6s' : '';
    const E = s => this.esc(s);
    let body;
    if (h.kind === 'phone') {
      body =
        '<div class="t-h3" style="margin:12px 0 8px;">' + E(h.title) + '</div>' +
        (h.desc ? '<div style="font-size:11px;letter-spacing:.14em;color:var(--washi-faint);text-transform:uppercase;margin-bottom:5px;">' + E(h.desc) + '</div>' : '') +
        '<div class="t-num" style="font-size:22px;color:' + accent + ';text-shadow:' + glow + ';margin-bottom:14px;letter-spacing:.01em;white-space:nowrap;">' + E(h.phone) + '</div>' +
        '<a class="btn btn--neon-line btn--sm" href="tel:' + E((h.phone || '').replace(/[^\d+]/g, '')) + '" style="width:100%;color:' + accent + ';border-color:' + line + ';">Позвонить</a>';
    } else {
      body =
        '<div class="t-h3" style="margin:12px 0 6px;">' + E(h.title) + '</div>' +
        '<div style="font-size:14px;color:var(--washi-dim);line-height:1.5;margin-bottom:16px;">' + E(h.desc) + '</div>' +
        '<a data-book-zone="' + E(h.label) + '" class="btn btn--sm" href="#" style="width:100%;">Забронировать стол</a>';
    }
    const el = document.createElement('div');
    el.className = 'mk-hs'; el.dataset.hs = h.id;
    el.innerHTML =
      '<div class="mk-dot-wrap" data-dot="' + E(h.id) + '">' +
        '<div style="position:absolute;left:50%;top:50%;width:46px;height:46px;border:1.6px solid ' + accent + ';border-radius:50%;animation:mkRing 2.6s ease-out infinite' + delay + ';"></div>' +
        '<div style="position:relative;display:flex;align-items:center;gap:9px;background:rgba(13,13,16,.92);border:1px solid ' + accent + ';padding:7px 13px 7px 9px;border-radius:999px;box-shadow:' + glow + ';white-space:nowrap;animation:mkBob 4s ease-in-out infinite' + delay + ';">' +
          '<span class="dot dot--pulse" style="color:' + accent + ';width:11px;height:11px;"></span>' +
          '<span style="font-weight:700;font-size:12px;letter-spacing:.16em;text-transform:uppercase;">' + E(h.label) + '</span>' +
        '</div>' +
      '</div>' +
      '<div class="mk-pop" data-pop="' + E(h.id) + '" style="width:' + (h.kind === 'phone' ? 268 : 262) + 'px;">' +
        '<div class="glass chamfer scanlines" style="--chamfer:14px;background:rgba(14,14,18,.95);padding:18px 18px 20px;border:1px solid ' + line + ';box-shadow:0 18px 50px rgba(0,0,0,.6),' + glow + ';">' +
          '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">' +
            '<span class="' + badge + '"><span class="dot"></span>' + E(h.label) + '</span>' +
            '<button data-act="close" style="background:none;border:0;color:var(--washi-faint);cursor:pointer;font-size:18px;line-height:1;padding:2px 4px;">×</button>' +
          '</div>' + body +
        '</div>' +
        '<div data-arrow="down" style="position:absolute;left:50%;bottom:-7px;width:14px;height:14px;background:rgba(14,14,18,.95);border-right:1px solid ' + line + ';border-bottom:1px solid ' + line + ';transform:translateX(-50%) rotate(45deg);"></div>' +
        '<div data-arrow="up" style="display:none;position:absolute;left:50%;top:-7px;width:14px;height:14px;background:rgba(14,14,18,.95);border-left:1px solid ' + line + ';border-top:1px solid ' + line + ';transform:translateX(-50%) rotate(45deg);"></div>' +
      '</div>';
    return el;
  }

  /* ── Доступность столов на выбранный день (this.bkDate) ── */
  todayISO() { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }
  readBookings() { try { return JSON.parse(localStorage.getItem('mirai_bookings_v1')) || []; } catch (e) { return []; } }
  fmtDay(iso) {
    const MON = ['янв','фев','мар','апр','мая','июн','июл','авг','сен','окт','ноя','дек'];
    const p = (iso || this.todayISO()).split('-'); return (+p[2]) + ' ' + MON[(+p[1])-1];
  }
  dayBookingsFor(id) { return (this._dayBookings && this._dayBookings[id]) || []; }
  applyBookingStatuses() {
    const day = this.bkDate || this.todayISO();
    const bk = this.readBookings().filter(b => b.dateISO === day && b.status !== 'cancelled');
    const byId = {}; bk.forEach(b => { (byId[b.tableId] = byId[b.tableId] || []).push(b); });
    this._dayBookings = byId;
    (this.tableEls || []).forEach(el => {
      const booked = byId[el.dataset.id];
      el.dataset.status = (booked && booked.length) ? 'busy' : 'free';
      this.styleTable(el);
    });
    const lab = this.root.querySelector('[data-map-daylabel]');
    if (lab) lab.textContent = this.fmtDay(day);
  }

  saveTables() {
    const cfg = this.readAdmin() || { positions: [] };
    if (!Array.isArray(cfg.positions)) cfg.positions = [];
    const byId = {}; cfg.positions.forEach(p => byId[p.id] = p);
    this.tableEls.forEach(el => {
      const id = el.dataset.id;
      let p = byId[id];
      if (!p) { p = { id }; byId[id] = p; cfg.positions.push(p); }
      p.label = el.dataset.label; p.status = el.dataset.status; p.shape = el.dataset.shape;
      p.x = +el.dataset.x; p.y = +el.dataset.y; p.w = +el.dataset.w; p.h = +el.dataset.h;
      p.radius = +el.dataset.radius; p.points = el.dataset.points;
    });
    try { localStorage.setItem('mirai_admin_v1', JSON.stringify(cfg)); } catch (e) {}
  }

  toggleEdit() {
    this.editMode = !this.editMode;
    const eb = this.root.querySelector('[data-act=edit]');
    if (this.editMode) {
      if (this.noteMode) this.disableNote();
      this.closePops();
      this.animate({ t: 1, scale: 1 }, 500);
      if (eb) { eb.textContent = '✓ Готово'; eb.style.background = 'var(--neon)'; eb.style.color = '#fff'; eb.style.borderColor = 'var(--neon)'; }
      if (this.editHint) this.editHint.style.display = 'flex';
    } else {
      if (eb) { eb.textContent = '✎ Править столы'; eb.style.background = 'rgba(17,18,21,.7)'; eb.style.color = 'var(--washi-dim)'; eb.style.borderColor = 'var(--sumi-400)'; }
      if (this.editHint) this.editHint.style.display = 'none';
      this.saveTables();
    }
    this.tableEls.forEach(el => { const p = el.querySelector('polygon'); if (p) p.style.cursor = this.editMode ? 'move' : 'pointer'; });
  }

  openTable(el) {
    const p = this.tablePop; if (!p) return;
    this.closePops();
    this._openTableId = el.dataset.id;
    p.querySelector('[data-table-num]').textContent = el.dataset.label || ('Стол ' + el.dataset.table);
    const badge = p.querySelector('[data-table-badge]');
    if (badge) { const st = el.dataset.status || 'busy'; badge.className = 'badge badge--' + (st==='free'?'free':st==='live'?'live':'busy'); }
    this.applyAdminMeta(p, el.dataset.id);
    // Бронь на этот стол в этот день не значит "весь день занят" — брони на
    // разное время не пересекаются (см. tableConflict в booking.js). Тут
    // только показываем уже занятые часы; сам конфликт ловится при выборе
    // времени в флоу брони (слоты гасятся) и окончательно — на сервере.
    const booked = this.dayBookingsFor(el.dataset.id);
    const descEl = p.querySelector('[data-table-desc]');
    const call = p.querySelector('[data-table-call]');
    if (descEl) {
      const times = booked.map(b => b.time).filter(Boolean).sort();
      descEl.innerHTML = (times.length ? '<span style="color:var(--neon);font-weight:700;">Занято: ' + times.join(', ') + '</span><br>' : '') +
        '<span style="color:var(--washi-faint);">Lounge · бронирование стола заранее</span>';
    }
    if (call) {
      call.dataset.booked = '0';
      call.textContent = 'Забронировать';
      call.style.opacity = '';
      call.style.pointerEvents = '';
      call.style.cursor = '';
    }
    p.style.left = (el.dataset.x * 100) + '%';
    p.style.top = (el.dataset.y * 100) + '%';
    p.dataset.open = '1'; p.style.opacity = '1'; p.style.pointerEvents = 'auto';
    this.placePop(p, el.getBoundingClientRect());
    this.openPopKey = 'table';
  }

  goToZone(k) {
    this.closePops();
    const pref = this.hs[k].pref;
    this.animate({ t: pref, scale: 1 }, 650, () => this.togglePop(k));
  }

  applyAdminMeta(pop, id) {
    let cfg; try { cfg = JSON.parse(localStorage.getItem('mirai_admin_v1')); } catch (e) {}
    const pos = cfg && cfg.positions && cfg.positions.find(x => x.id === id);
    const ph = pop.querySelector('[data-table-photo]');
    const desc = pop.querySelector('[data-table-desc]');
    const call = pop.querySelector('[data-table-call]');
    if (ph) {
      const img = ph.querySelector('img');
      if (pos && pos.photo) { img.src = pos.photo; ph.style.display = 'block'; }
      else { img.removeAttribute('src'); ph.style.display = 'none'; }
    }
    if (desc && pos && pos.desc) desc.textContent = pos.desc;
    if (call && pos && pos.phone) call.setAttribute('href', 'tel:' + pos.phone.replace(/\s+/g, ''));
  }

  animate(target, dur, done) {
    cancelAnimationFrame(this._raf);
    const from = { ...this.v }; const t0 = performance.now();
    const step = now => {
      const p = Math.min(1, (now - t0) / dur);
      const e = p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
      for (const k in target) this.v[k] = from[k] + (target[k] - from[k]) * e;
      if ((target.scale != null ? target.scale : this.v.scale) <= 1.02) {
        this.v.tx = from.tx * (1 - e); this.v.ty = from.ty * (1 - e);
      }
      this.apply();
      if (p < 1) this._raf = requestAnimationFrame(step); else if (done) done();
    };
    this._raf = requestAnimationFrame(step);
  }

  hideHint() { if (this.hint && !this._hintGone) { this._hintGone = true; this.hint.style.opacity = '0'; } }

  // Текущий вид по положению камеры: A (ближе к началу) или B.
  curView() { return this.v.t < 0.5 ? 'a' : 'b'; }

  // Показываем только заметки текущего вида (вид A ↔ вид B — разные наборы).
  updateNoteVis() {
    const cv = this.curView();
    (this.noteEls || []).forEach(el => { el.style.display = (el.dataset.view || 'a') === cv ? '' : 'none'; });
    if (this.openPopKey === 'note' && this._openNoteEl && (this._openNoteEl.dataset.view || 'a') !== cv) this.closePops();
  }

  loadNotes() { try { this.notes = JSON.parse(localStorage.getItem('mirai_notes_v1')) || []; } catch (e) { this.notes = []; } if (!Array.isArray(this.notes)) this.notes = []; }
  saveNotes() { try { localStorage.setItem('mirai_notes_v1', JSON.stringify(this.notes)); } catch (e) {} }

  buildNotes() {
    this.world.querySelectorAll('.mk-note').forEach(el => el.remove());
    this.noteEls = [];
    this.notes.forEach(n => { const el = this.makeNoteEl(n); this.world.appendChild(el); this.noteEls.push(el); this.layoutNote(el); });
    this.updateNoteVis();
  }

  makeNoteEl(n) {
    const el = document.createElement('div');
    el.className = 'mk-note'; el.dataset.note = n.id; el.dataset.x = n.x; el.dataset.y = n.y; el.dataset.view = n.view || 'a';
    el.innerHTML = '<div class="mk-pin"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#F5007D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></div>';
    return el;
  }

  layoutNote(el) { el.style.left = (+el.dataset.x * 100) + '%'; el.style.top = (+el.dataset.y * 100) + '%'; }

  addNoteAt(clientX, clientY) {
    const r = this.world.getBoundingClientRect();
    const x = Math.max(0.02, Math.min(0.98, (clientX - r.left) / r.width));
    const y = Math.max(0.02, Math.min(0.98, (clientY - r.top) / r.height));
    const n = { id: 'n' + Date.now().toString(36) + Math.floor(Math.random() * 1000), x, y, text: '', photo: '', view: this.curView() };
    this.notes.push(n); this.saveNotes();
    const el = this.makeNoteEl(n); this.world.appendChild(el); this.noteEls.push(el); this.layoutNote(el);
    this.openNote(el, true);
  }

  bindNotePop() {
    const p = this.notePop; if (!p) return;
    this._workPhoto = '';
    p.querySelector('[data-note-act=close]').addEventListener('click', e => { e.stopPropagation(); this.closePops(); });
    p.querySelector('[data-note-act=edit]').addEventListener('click', e => { e.stopPropagation(); this.showNoteEditor(true); });
    p.querySelector('[data-note-act=save]').addEventListener('click', e => { e.stopPropagation(); this.saveNoteEdits(); });
    p.querySelector('[data-note-act=delete]').addEventListener('click', e => { e.stopPropagation(); this.deleteNote(); });
    p.querySelector('[data-note-act=rmphoto]').addEventListener('click', e => { e.stopPropagation(); this._workPhoto = ''; this.renderEditPhoto(); });
    p.querySelector('[data-note-file]').addEventListener('change', e => this.onNotePhoto(e));
    p.addEventListener('pointerdown', e => e.stopPropagation());
  }

  openNote(el, edit) {
    const p = this.notePop; if (!p) return;
    this.closePops();
    const n = this.notes.find(x => x.id === el.dataset.note); if (!n) return;
    this._openNote = n; this._openNoteEl = el; this._workPhoto = n.photo || '';
    // Кнопку «✎» и редактор показывает только showNoteEditor с учётом isAdmin.
    this.fillNoteRead(n);
    this.showNoteEditor(!!edit && this.isAdmin);
    p.style.left = (n.x * 100) + '%'; p.style.top = (n.y * 100) + '%';
    p.dataset.open = '1'; p.style.opacity = '1'; p.style.pointerEvents = 'auto';
    this.placePop(p, el.getBoundingClientRect());
    this.openPopKey = 'note';
  }

  fillNoteRead(n) {
    const p = this.notePop;
    const tr = p.querySelector('[data-note-text-r]');
    const has = n.text && n.text.trim();
    tr.textContent = has ? n.text : 'Без комментария';
    tr.style.color = has ? 'var(--washi-dim)' : 'var(--washi-faint)';
    const ph = p.querySelector('[data-note-photo-r]');
    const img = ph.querySelector('img');
    if (n.photo) { img.src = n.photo; ph.style.display = 'block'; } else { img.removeAttribute('src'); ph.style.display = 'none'; }
  }

  showNoteEditor(edit) {
    const p = this.notePop;
    p.querySelector('[data-note-read]').style.display = edit ? 'none' : 'block';
    p.querySelector('[data-note-edit]').style.display = edit ? 'block' : 'none';
    p.querySelector('[data-note-act=edit]').style.display = (edit || !this.isAdmin) ? 'none' : 'block';
    if (edit && this._openNote) {
      p.querySelector('[data-note-input]').value = this._openNote.text || '';
      this._workPhoto = this._openNote.photo || '';
      this.renderEditPhoto();
      setTimeout(() => { try { p.querySelector('[data-note-input]').focus(); } catch (e) {} }, 30);
    } else if (this._openNoteEl) {
      this.placePop(p, this._openNoteEl.getBoundingClientRect());
    }
  }

  renderEditPhoto() {
    const p = this.notePop;
    const box = p.querySelector('[data-note-photo-e]');
    const img = box.querySelector('img');
    if (this._workPhoto) { img.src = this._workPhoto; box.style.display = 'block'; } else { img.removeAttribute('src'); box.style.display = 'none'; }
    if (this._openNoteEl) this.placePop(p, this._openNoteEl.getBoundingClientRect());
  }

  onNotePhoto(e) {
    const f = e.target.files && e.target.files[0]; if (!f) return;
    const fr = new FileReader();
    fr.onload = () => {
      const img = new Image();
      img.onload = () => {
        const max = 1000; const s = Math.min(1, max / Math.max(img.width, img.height));
        const cv = document.createElement('canvas'); cv.width = Math.round(img.width * s); cv.height = Math.round(img.height * s);
        cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
        this._workPhoto = cv.toDataURL('image/jpeg', 0.82);
        this.renderEditPhoto();
      };
      img.src = fr.result;
    };
    fr.readAsDataURL(f);
    e.target.value = '';
  }

  saveNoteEdits() {
    const n = this._openNote; if (!n) return;
    n.text = this.notePop.querySelector('[data-note-input]').value;
    n.photo = this._workPhoto || '';
    this.saveNotes();
    this.fillNoteRead(n);
    this.showNoteEditor(false);
  }

  deleteNote() {
    const n = this._openNote; if (!n) return;
    this.notes = this.notes.filter(x => x.id !== n.id);
    this.saveNotes();
    if (this._openNoteEl) { this._openNoteEl.remove(); this.noteEls = (this.noteEls || []).filter(el => el !== this._openNoteEl); }
    this._openNote = null; this._openNoteEl = null;
    this.closePops();
  }

  disableNote() {
    this.noteMode = false;
    const nb = this.root.querySelector('[data-act=note]');
    if (nb) { nb.textContent = '＋ Заметка'; nb.style.background = 'rgba(17,18,21,.7)'; nb.style.color = 'var(--washi-dim)'; nb.style.borderColor = 'var(--sumi-400)'; }
    if (this.noteHint) this.noteHint.style.display = 'none';
    this.stage.style.cursor = 'grab';
  }

  toggleNote() {
    this.noteMode = !this.noteMode;
    this.closePops();
    if (this.noteMode) {
      if (this.editMode) this.toggleEdit();
      const nb = this.root.querySelector('[data-act=note]');
      if (nb) { nb.textContent = '✓ Готово'; nb.style.background = 'var(--neon)'; nb.style.color = '#fff'; nb.style.borderColor = 'var(--neon)'; }
      if (this.noteHint) this.noteHint.style.display = 'flex';
      this.stage.style.cursor = 'crosshair';
    } else {
      this.disableNote();
    }
  }
}


/* ── Инициализация флоу брони (как в проекте «Бронирование») ── */
MiraiBooking.init({
  venueName: "MIRAI LOUNGE",
  fab: false,                       // на карте своя кнопка брони — плавающую не показываем
  daysAhead: 14, openHour: 16, closeHour: 5, slotStepMin: 30,
  minGuests: 1, maxGuests: 20,
  transport: { mode:"demo", telegram:{ botToken:"", chatId:"" }, email:{ endpoint:"" }, endpoint:"" },
  onSuccess: function(d){ console.log("Бронь оформлена:", d); if (window.__mk) window.__mk.applyBookingStatuses(); }
});

/* ── Монтаж 3D-карты ── */
var rootEl = document.querySelector('[data-ref="setRoot"]');
window.__mk = new Component(rootEl);

/* ── Онлайн-синхронизация: подтянуть брони с сервера и перекрашивать столы ── */
if (window.MiraiStore && MiraiStore.online && MiraiStore.online()) MiraiStore.start("public");
window.addEventListener('mirai-sync', function(){ if (window.__mk) window.__mk.applyBookingStatuses(); });

/* ── Стыковка: кнопки «Забронировать» открывают booking-флоу ── */
document.addEventListener('click', function(e){
  // конкретный стол (из попапа стола)
  var tcall = e.target.closest && e.target.closest('[data-table-call]');
  if (tcall) {
    e.preventDefault();
    var mk = window.__mk, id = mk._openTableId;
    var el = (mk.tableEls || []).filter(function(t){ return t.dataset.id === id; })[0];
    var label = el ? (el.dataset.label || ('Стол ' + el.dataset.table)) : 'Стол';
    var seats = el ? +el.dataset.seats : null;
    MiraiBooking.open({ table: { id: id, label: label, zone: 'Lounge', seats: seats }, dateISO: mk.bkDate });
    mk.closePops();
    return;
  }
  // «Забронировать стол» в попапе Lounge — переводим карту на вид B (показ столов),
  // гость дальше выбирает конкретный стол на карте.
  var zcall = e.target.closest && e.target.closest('[data-book-zone]');
  if (zcall) {
    e.preventDefault();
    var mk2 = window.__mk;
    if (mk2) { mk2.closePops(); mk2.animate({ t: 1, scale: 1 }, 650); }
    return;
  }
});
