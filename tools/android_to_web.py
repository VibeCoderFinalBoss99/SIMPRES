"""Mengubah sumber tampilan aplikasi Android (layout, drawable, warna, gaya, teks) menjadi file web
untuk SIMPRES versi web (backend/public/app/gen). Jalankan ulang setiap kali tampilan Android berubah:

    python tools/android_to_web.py

Hasilnya dipakai apa adanya oleh backend/public/app (tanpa build tool di server).
"""
import html
import json
import re
import shutil
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RES = ROOT / 'app/src/main/res'
OUT = ROOT / 'backend/public/app/gen'
A = '{http://schemas.android.com/apk/res/android}'
APP = '{http://schemas.android.com/apk/res-auto}'
AAPT = '{http://schemas.android.com/aapt}'


def css_name(name):
    return name.replace('.', '_')


# ------------------------------------------------------------------ warna
def read_colors(path):
    colors = {}
    for c in ET.parse(path).getroot().findall('color'):
        colors[c.get('name')] = c.text.strip()
    return colors


def hex_css(value):
    """#AARRGGBB (Android) menjadi rgba() CSS."""
    v = value.lstrip('#')
    if len(v) == 8:
        a, r, g, b = int(v[0:2], 16), int(v[2:4], 16), int(v[4:6], 16), int(v[6:8], 16)
        return f'rgba({r},{g},{b},{a / 255:.3f})'
    if len(v) == 3:
        v = ''.join(ch * 2 for ch in v)
    return '#' + v


def color(ref):
    """@color/x -> var(--x); #hex -> css; @android:color/white -> #fff."""
    if ref is None:
        return None
    if ref.startswith('@color/'):
        return f'var(--{ref[7:]})'
    if ref.startswith('@android:color/'):
        return {'white': '#FFFFFF', 'black': '#000000', 'transparent': 'transparent'}[ref[15:]]
    if ref.startswith('#'):
        return hex_css(ref)
    return None


# ------------------------------------------------------------------ teks
def unescape(text):
    text = (text or '').strip()
    if len(text) >= 2 and text[0] == '"' and text[-1] == '"':
        text = text[1:-1]
    return (text.replace("\\'", "'").replace('\\"', '"').replace('\\n', '\n')
            .replace('\\t', '\t').replace('\\@', '@').replace('\\?', '?'))


def read_strings():
    strings = {}
    for f in ['strings.xml', 'messages.xml']:
        root = ET.parse(RES / 'values' / f).getroot()
        for s in root.findall('string'):
            strings[s.get('name')] = unescape(''.join(s.itertext()))
        for p in root.findall('plurals'):
            items = {i.get('quantity'): unescape(i.text) for i in p.findall('item')}
            strings[p.get('name')] = items.get('other') or next(iter(items.values()))
    return strings


STRINGS = read_strings()


def text(ref):
    if ref is None:
        return ''
    if ref.startswith('@string/'):
        return STRINGS.get(ref[8:], '')
    return unescape(ref)


def dp(value):
    if value is None:
        return None
    m = re.fullmatch(r'(-?[\d.]+)(dp|dip|sp|px)', value)
    return float(m.group(1)) if m else None


def px(value):
    n = dp(value)
    return None if n is None else f'{n:g}px'


# ------------------------------------------------------------------ gaya teks
STYLES = {}
for s in ET.parse(RES / 'values/styles.xml').getroot().findall('style'):
    STYLES[s.get('name')] = {'parent': s.get('parent'), 'items': {i.get('name'): (i.text or '').strip() for i in s.findall('item')}}


def style_items(name):
    """Semua item gaya, termasuk dari induk (T.Display mewarisi T)."""
    if name not in STYLES:
        return {}
    st = STYLES[name]
    parent = st['parent'] or (name.rsplit('.', 1)[0] if '.' in name else None)
    items = dict(style_items(parent)) if parent else {}
    items.update(st['items'])
    return items


FONTS = {'@font/lexend_light': 300, '@font/lexend_regular': 400, '@font/lexend_medium': 500, '@font/lexend_semibold': 600}


# ------------------------------------------------------------------ drawable
VSIZE = {}
GRAD_ID = [0]
DRAWABLES = {}  # name -> ('vector', svg) | ('shape', css) | ('selector', ...)


def shape_css(el):
    css = {}
    kind = el.get(A + 'shape', 'rectangle')
    solid = el.find('solid')
    if solid is not None:
        css['background-color'] = color(solid.get(A + 'color'))
    grad = el.find('gradient')
    if grad is not None:
        angle = int(grad.get(A + 'angle', '0'))
        start, end = color(grad.get(A + 'startColor')), color(grad.get(A + 'endColor'))
        center = color(grad.get(A + 'centerColor'))
        stops = ', '.join(x for x in [start, center, end] if x)
        if grad.get(A + 'type') == 'radial':
            css['background-image'] = f'radial-gradient(circle, {stops})'
        else:
            css['background-image'] = f'linear-gradient({(90 - angle) % 360}deg, {stops})'
    stroke = el.find('stroke')
    if stroke is not None:
        style = 'dashed' if stroke.get(A + 'dashWidth') else 'solid'
        css['border'] = f'{px(stroke.get(A + "width"))} {style} {color(stroke.get(A + "color"))}'
    corners = el.find('corners')
    if kind == 'oval':
        css['border-radius'] = '50%'
    elif corners is not None:
        r = corners.get(A + 'radius')
        if r:
            css['border-radius'] = px(r)
        else:
            tl, tr = px(corners.get(A + 'topLeftRadius')) or '0', px(corners.get(A + 'topRightRadius')) or '0'
            br, bl = px(corners.get(A + 'bottomRightRadius')) or '0', px(corners.get(A + 'bottomLeftRadius')) or '0'
            css['border-radius'] = f'{tl} {tr} {br} {bl}'
    return {k: v for k, v in css.items() if v}


def css_text(css):
    return ';'.join(f'{k}:{v}' for k, v in css.items())


def vector_svg(root):
    vw, vh = root.get(A + 'viewportWidth'), root.get(A + 'viewportHeight')
    defs = []

    def paint(el, attr, prefix):
        value = el.get(A + attr)
        if value:
            return color(value)
        g = el.find(f"{AAPT}attr[@name='android:{attr}']/gradient")
        if g is not None:
            GRAD_ID[0] += 1
            gid = f'grad{GRAD_ID[0]}'
            stops = ''.join(f'<stop offset="{o}" stop-color="{color(g.get(A + k))}"/>'
                            for o, k in [('0', 'startColor'), ('1', 'endColor')])
            defs.append(f'<linearGradient id="{gid}" gradientUnits="userSpaceOnUse" x1="{g.get(A + "startX")}" '
                        f'y1="{g.get(A + "startY")}" x2="{g.get(A + "endX")}" y2="{g.get(A + "endY")}">{stops}</linearGradient>')
            return f'url(#{gid})'
        return None

    def node(el):
        tag = el.tag
        if tag == 'path':
            attrs = {'d': el.get(A + 'pathData')}
            fill = paint(el, 'fillColor', 'f')
            attrs['fill'] = fill or 'none'
            stroke = paint(el, 'strokeColor', 's')
            if stroke:
                attrs['stroke'] = stroke
                attrs['stroke-width'] = el.get(A + 'strokeWidth', '1')
                if el.get(A + 'strokeLineCap'):
                    attrs['stroke-linecap'] = el.get(A + 'strokeLineCap')
                if el.get(A + 'strokeLineJoin'):
                    attrs['stroke-linejoin'] = el.get(A + 'strokeLineJoin')
            if el.get(A + 'fillAlpha'):
                attrs['fill-opacity'] = el.get(A + 'fillAlpha')
            if el.get(A + 'strokeAlpha'):
                attrs['stroke-opacity'] = el.get(A + 'strokeAlpha')
            if el.get(A + 'fillType') == 'evenOdd':
                attrs['fill-rule'] = 'evenodd'
            return '<path ' + ' '.join(f'{k}="{html.escape(v)}"' for k, v in attrs.items()) + '/>'
        if tag == 'group':
            tx, ty = el.get(A + 'translateX', '0'), el.get(A + 'translateY', '0')
            sx, sy = el.get(A + 'scaleX', '1'), el.get(A + 'scaleY', '1')
            rot = el.get(A + 'rotation', '0')
            pvx, pvy = el.get(A + 'pivotX', '0'), el.get(A + 'pivotY', '0')
            t = f'translate({tx} {ty}) translate({pvx} {pvy}) rotate({rot}) scale({sx} {sy}) translate(-{pvx} -{pvy})'
            return f'<g transform="{t}">' + ''.join(node(c) for c in el) + '</g>'
        return ''

    body = ''.join(node(c) for c in root)
    d = f'<defs>{"".join(defs)}</defs>' if defs else ''
    return f'<svg viewBox="0 0 {vw} {vh}" xmlns="http://www.w3.org/2000/svg">{d}{body}</svg>'


for f in sorted((RES / 'drawable').glob('*.xml')):
    root = ET.parse(f).getroot()
    name = f.stem
    if root.tag == 'vector':
        DRAWABLES[name] = ('vector', vector_svg(root))
        VSIZE[name] = dp(root.get(A + 'width')) or 24
    elif root.tag == 'shape':
        DRAWABLES[name] = ('shape', shape_css(root))
    elif root.tag == 'selector':
        states = []
        for item in root.findall('item'):
            ref = item.get(A + 'drawable')
            shape = item.find('shape')
            css = shape_css(shape) if shape is not None else ref[10:]
            state = 'selected' if item.get(A + 'state_selected') == 'true' else ('checked' if item.get(A + 'state_checked') == 'true' else '')
            states.append((state, css))
        DRAWABLES[name] = ('selector', states)
    elif root.tag == 'inset':
        DRAWABLES[name] = ('shape', shape_css(root.find('shape')))
    elif root.tag == 'layer-list':
        DRAWABLES[name] = ('special', None)


def drawables_css():
    out = []
    for name, (kind, data) in DRAWABLES.items():
        if kind == 'shape':
            out.append(f'.bg-{name}{{{css_text(data)}}}')
        elif kind == 'selector':
            for state, css in data:
                sel = f'.bg-{name}' + (f'.{state}' if state else '')
                if isinstance(css, str):  # merujuk drawable lain
                    css = DRAWABLES[css][1]
                out.append(f'{sel}{{{css_text(css)}}}')
    # Garis atas navigasi bawah (layer-list di Android).
    out.append('.bg-bg_bottom_nav{background-color:var(--surface);box-shadow:inset 0 1px 0 var(--divider)}')
    return '\n'.join(out)


# ------------------------------------------------------------------ layout
GRAVITY_H = {'center': 'center', 'center_horizontal': 'center', 'end': 'flex-end', 'right': 'flex-end', 'start': 'flex-start', 'left': 'flex-start'}
GRAVITY_V = {'center': 'center', 'center_vertical': 'center', 'bottom': 'flex-end', 'top': 'flex-start'}


def gravity_parts(value):
    parts = (value or '').split('|')
    h = next((GRAVITY_H[p] for p in parts if p in GRAVITY_H), None)
    v = next((GRAVITY_V[p] for p in parts if p in GRAVITY_V), None)
    return h, v


SELF = {'flex-start': 'start', 'flex-end': 'end', 'center': 'center'}


def g(attrs, key, style=None):
    v = attrs.get(A + key)
    if v is None and style is not None:
        v = style.get('android:' + key)
    return v


def element(el, parent_kind, overrides=None, root=False):
    """Satu elemen layout Android menjadi HTML. parent_kind: 'v', 'h', 'frame', 'scroll', 'hscroll'."""
    tag = el.tag
    if tag == 'include':
        layout = el.get('layout').split('/')[-1]
        inc = ET.parse(RES / 'layout' / f'{layout}.xml').getroot()
        ov = {k: v for k, v in el.attrib.items() if k != 'layout'}
        return element(inc, parent_kind, ov)

    attrs = dict(el.attrib)
    if overrides:
        attrs.update(overrides)
    style = {}
    st = attrs.get('style')
    if st:
        style = style_items(st.split('/')[-1])
    short = tag.split('.')[-1]

    css = {}
    classes = []
    html_attrs = {}
    inner = ''

    # ---- ukuran dan posisi di dalam induk
    w, h = g(attrs, 'layout_width', style), g(attrs, 'layout_height', style)
    weight = g(attrs, 'layout_weight')
    lg_h, lg_v = gravity_parts(g(attrs, 'layout_gravity'))
    if root:
        css['width'] = '100%'
        css['height'] = '100%' if h == 'match_parent' else None
    if parent_kind in ('v', 'scroll'):
        if w == 'match_parent':
            css['align-self'] = 'stretch'
        elif px(w):
            css['width'] = px(w)
        if weight and h in ('0dp', 'match_parent'):
            css['flex'] = f'{weight} 1 0'
            css['min-height'] = '0'
        elif h == 'match_parent':
            css['flex'] = '1 1 0' if parent_kind == 'v' else '1 0 auto'
        else:
            css['flex-shrink'] = '0'
            if px(h):
                css['height'] = px(h)
        if lg_h:
            css['align-self'] = lg_h
    elif parent_kind in ('h', 'hscroll'):
        if h == 'match_parent':
            css['align-self'] = 'stretch'
        elif px(h):
            css['height'] = px(h)
        if weight:
            css['flex'] = f'{weight} 1 0' if w == '0dp' else f'{weight} 1 auto'
            css['min-width'] = '0'
        elif w == 'match_parent':
            css['flex'] = '1 1 0'
            css['min-width'] = '0'
        else:
            css['flex-shrink'] = '0'
            if px(w):
                css['width'] = px(w)
        if lg_v:
            css['align-self'] = lg_v
    elif parent_kind == 'frame':
        css['grid-area'] = '1/1'
        css['justify-self'] = 'stretch' if w == 'match_parent' else SELF.get(lg_h or 'flex-start')
        css['align-self'] = 'stretch' if h == 'match_parent' else SELF.get(lg_v or 'flex-start')
        if px(w):
            css['width'] = px(w)
        if px(h):
            css['height'] = px(h)

    # ---- margin dan padding
    m_all = px(g(attrs, 'layout_margin'))
    mt, mb = px(g(attrs, 'layout_marginTop')) or m_all, px(g(attrs, 'layout_marginBottom')) or m_all
    ms, me = px(g(attrs, 'layout_marginStart')) or m_all, px(g(attrs, 'layout_marginEnd')) or m_all
    for k, v in [('margin-top', mt), ('margin-bottom', mb), ('margin-left', ms), ('margin-right', me)]:
        if v:
            css[k] = v
    p_all = px(g(attrs, 'padding', style))
    for k, key in [('padding-top', 'paddingTop'), ('padding-bottom', 'paddingBottom'), ('padding-left', 'paddingStart'), ('padding-right', 'paddingEnd')]:
        v = px(g(attrs, key, style)) or p_all
        if v:
            css[k] = v
    if g(attrs, 'minHeight', style):
        css['min-height'] = px(g(attrs, 'minHeight', style))

    # ---- latar
    bg = g(attrs, 'background', style)
    if bg:
        if bg.startswith('@drawable/'):
            classes.append('bg-' + bg[10:])
        else:
            css['background-color'] = color(bg)
    tint_bg = g(attrs, 'backgroundTint')
    if tint_bg:
        css['background-color'] = color(tint_bg)
        css['background-image'] = 'none'
    if g(attrs, 'elevation'):
        css['box-shadow'] = '0 2px 8px rgba(16,19,23,.12)'
    if g(attrs, 'rotation'):
        css['transform'] = f'rotate({g(attrs, "rotation")}deg)'
    vis = g(attrs, 'visibility')
    if vis == 'gone':
        classes.append('gone')
    elif vis == 'invisible':
        classes.append('invisible')

    vid = g(attrs, 'id')
    if vid:
        html_attrs['id'] = vid.split('/')[-1]
    if g(attrs, 'tag'):
        html_attrs['data-tag'] = g(attrs, 'tag')
    desc = g(attrs, 'contentDescription')
    if desc:
        html_attrs['aria-label'] = text(desc)

    tagname = 'div'
    gh, gv = gravity_parts(g(attrs, 'gravity', style))

    if short in ('LinearLayout', 'ScrollView', 'HorizontalScrollView', 'FrameLayout', 'ViewPager', 'FragmentContainerView'):
        children = [c for c in el if isinstance(c.tag, str)]
        if short == 'LinearLayout':
            kind = 'h' if g(attrs, 'orientation') == 'horizontal' else 'v'
            classes.insert(0, 'll-' + kind)
            main, cross = (gv, gh) if kind == 'v' else (gh, gv)
            if main:
                css['justify-content'] = main
            if cross:
                css['align-items'] = cross
        elif short == 'ScrollView':
            kind = 'scroll'
            classes.insert(0, 'sv')
            if g(attrs, 'fillViewport') == 'true':
                classes.append('fill')
        elif short == 'HorizontalScrollView':
            kind = 'hscroll'
            classes.insert(0, 'hsv')
        else:
            kind = 'frame'
            classes.insert(0, 'fl')
            css['grid-template-columns'] = 'auto' if w == 'wrap_content' else 'minmax(0,1fr)'
            css['grid-template-rows'] = 'auto' if h == 'wrap_content' else 'minmax(0,1fr)'
        inner = ''.join(element(c, kind) for c in children)
    elif short in ('TextView', 'Button'):
        tagname = 'button' if short == 'Button' else 'div'
        classes.insert(0, 'btn' if short == 'Button' else 'tv')
        if st:
            classes.append('s-' + css_name(st.split('/')[-1]))
        text_css(attrs, style, css)
        if gh or gv:
            css['text-align'] = {'center': 'center', 'flex-end': 'right', 'flex-start': 'left'}.get(gh or 'flex-start')
            if gv and gv != 'flex-start' or short == 'Button':
                css['display'] = 'flex'
                css['flex-direction'] = 'column' if not (g(attrs, 'drawableStart', style) or g(attrs, 'drawableEnd', style)) else 'row'
                if css['flex-direction'] == 'column':
                    css['justify-content'] = gv or 'center'
                    css['align-items'] = 'stretch'
                else:
                    css['justify-content'] = gh or 'center'
                    css['align-items'] = gv or 'center'
        if g(attrs, 'maxLines', style) == '1' or g(attrs, 'singleLine') == 'true':
            classes.append('one-line')
        t = html.escape(text(g(attrs, 'text')))
        ds, de = g(attrs, 'drawableStart', style), g(attrs, 'drawableEnd', style)
        dtint = color(g(attrs, 'drawableTint', style))
        pad = px(g(attrs, 'drawablePadding', style)) or '0px'
        if ds or de:
            css.setdefault('display', 'flex')
            css.setdefault('align-items', 'center')
            css['gap'] = pad
            t = f'<span class="txt">{t}</span>'
        if ds:
            t = icon_html(ds[10:], dtint, 'dr') + t
        if de:
            t = t + icon_html(de[10:], dtint, 'dr')
        inner = t
        if short == 'Button':
            html_attrs['type'] = 'button'
    elif short == 'EditText':
        classes.insert(0, 'et')
        if st:
            classes.append('s-' + css_name(st.split('/')[-1]))
        text_css(attrs, style, css)
        itype = g(attrs, 'inputType', style) or ''
        multi = 'textMultiLine' in itype or g(attrs, 'minLines')
        ds = g(attrs, 'drawableStart', style)
        dtint = color(g(attrs, 'drawableTint', style))
        pad = px(g(attrs, 'drawablePadding', style)) or '0px'
        css['gap'] = pad
        field_attrs = {'placeholder': text(g(attrs, 'hint'))}
        if vid:
            field_attrs['id'] = html_attrs.pop('id')
        value = text(g(attrs, 'text'))
        if multi:
            field = f'<textarea {attr_str(field_attrs)} rows="{g(attrs, "minLines") or 3}">{html.escape(value)}</textarea>'
            if gv == 'flex-start' or 'top' in (g(attrs, 'gravity', style) or ''):
                css['align-items'] = 'flex-start'
        else:
            field_attrs['type'] = 'password' if 'Password' in itype else ('email' if 'Email' in itype else 'text')
            if 'number' in itype or 'phone' in itype:
                field_attrs['inputmode'] = 'numeric'
            field_attrs['value'] = value
            field = f'<input {attr_str(field_attrs)}>'
        tagname = 'label'
        inner = (icon_html(ds[10:], dtint, 'dr') if ds else '') + field
    elif short == 'ImageView':
        classes.insert(0, 'iv')
        tint = color(g(attrs, 'tint', style))
        src = g(attrs, 'src') or g(attrs, 'srcCompat', style)
        if tint:
            classes.append('tint')
            css['color'] = tint
        if src and src.startswith('@mipmap/'):
            inner = mipmap_svg(src.endswith('_round'))
        elif src and src.startswith('@drawable/'):
            name = src[10:]
            html_attrs['data-src'] = name
            inner = icon_inner(name)
        if not w or w == 'wrap_content':
            css.setdefault('width', '24px')
        if not h or h == 'wrap_content':
            css.setdefault('height', '24px')
        if g(attrs, 'padding', style):
            css['box-sizing'] = 'border-box'
    elif short == 'SwitchCompat':
        tagname = 'label'
        classes.insert(0, 'switch')
        checked = ' checked' if g(attrs, 'checked') == 'true' else ''
        fid = html_attrs.pop('id', '')
        inner = f'<input type="checkbox" id="{fid}"{checked}><span></span>'
    elif short == 'LottieAnimationView':
        classes.insert(0, 'lottie')
        raw = attrs.get(APP + 'lottie_rawRes')
        if raw:
            html_attrs['data-raw'] = raw.split('/')[-1]
        if attrs.get(APP + 'lottie_loop') == 'true':
            html_attrs['data-loop'] = '1'
    else:  # View biasa
        classes.insert(0, 'v')

    html_attrs['class'] = ' '.join(classes)
    style_attr = css_text({k: v for k, v in css.items() if v})
    if style_attr:
        html_attrs['style'] = style_attr
    return f'<{tagname} {attr_str(html_attrs)}>{inner}</{tagname}>'


def mipmap_svg(round_icon):
    """Ikon launcher (adaptive icon) seperti tampil di HP: area tengah 72 dari 108, bulat atau kotak membulat."""
    inner = lambda name: re.sub(r'^<svg[^>]*>|</svg>$', '', DRAWABLES[name][1])
    shape = '<circle cx="54" cy="54" r="36"/>' if round_icon else '<rect x="18" y="18" width="72" height="72" rx="16"/>'
    cid = 'launcherClip' + ('R' if round_icon else 'S')
    return (f'<svg viewBox="18 18 72 72" xmlns="http://www.w3.org/2000/svg"><defs><clipPath id="{cid}">{shape}</clipPath></defs>'
            f'<g clip-path="url(#{cid})">{inner("ic_launcher_background")}{inner("ic_launcher_foreground")}</g></svg>')


def attr_str(attrs):
    return ' '.join(f'{k}="{html.escape(str(v), quote=True)}"' for k, v in attrs.items() if v is not None)


def text_css(attrs, style, css):
    if attrs.get(A + 'textSize'):
        css['font-size'] = px(attrs.get(A + 'textSize'))
    if attrs.get(A + 'textColor'):
        c = attrs.get(A + 'textColor')
        if c in ('@color/option_text', '@color/tab_text'):
            pass  # warna ikut kelas .selected
        else:
            css['color'] = color(c)
    if attrs.get(A + 'fontFamily') in FONTS:
        css['font-weight'] = str(FONTS[attrs.get(A + 'fontFamily')])
    if attrs.get(A + 'letterSpacing'):
        css['letter-spacing'] = f'{float(attrs.get(A + "letterSpacing")):g}em'
    if attrs.get(A + 'lineSpacingMultiplier'):
        css['line-height'] = f'{1.25 * float(attrs.get(A + "lineSpacingMultiplier")):.3g}'


def icon_inner(name):
    kind, data = DRAWABLES.get(name, (None, None))
    if kind == 'vector':
        return data
    if kind == 'shape':
        return f'<span class="bg-{name}" style="display:block;width:100%;height:100%"></span>'
    return ''


def icon_html(name, tint, cls):
    size = VSIZE.get(name, 20)
    style = f' style="width:{size:g}px;height:{size:g}px' + (f';color:{tint}"' if tint else '"')
    t = ' tint' if tint else ''
    return f'<span class="iv {cls}{t}" data-src="{name}"{style}>{icon_inner(name)}</span>'


def styles_css():
    out = []
    for name in STYLES:
        items = style_items(name)
        css = {}
        if items.get('android:fontFamily') in FONTS:
            css['font-weight'] = str(FONTS[items['android:fontFamily']])
        if items.get('android:textSize'):
            css['font-size'] = px(items['android:textSize'])
        c = items.get('android:textColor')
        if c and c not in ('@color/option_text', '@color/tab_text'):
            css['color'] = color(c)
        if items.get('android:letterSpacing'):
            css['letter-spacing'] = f'{float(items["android:letterSpacing"]):g}em'
        if items.get('android:lineSpacingMultiplier'):
            css['line-height'] = f'{1.25 * float(items["android:lineSpacingMultiplier"]):.3g}'
        if items.get('android:minHeight'):
            css['min-height'] = px(items['android:minHeight'])
        for k, key in [('padding-left', 'paddingStart'), ('padding-right', 'paddingEnd'), ('padding-top', 'paddingTop'), ('padding-bottom', 'paddingBottom')]:
            if items.get('android:' + key):
                css[k] = px(items['android:' + key])
        if css:
            out.append(f'.s-{css_name(name)}{{{css_text(css)}}}')
    return '\n'.join(out)


def colors_css():
    light = read_colors(RES / 'values/colors.xml')
    dark = read_colors(RES / 'values-night/colors.xml')
    lines = [':root{' + ';'.join(f'--{k}:{hex_css(v)}' for k, v in light.items()) + '}']
    dark_vars = ';'.join(f'--{k}:{hex_css(v)}' for k, v in dark.items())
    lines.append(f':root.dark{{{dark_vars}}}')
    return '\n'.join(lines)


def fg_svg(w, h, size, x, y):
    """Logo putih ic_launcher_foreground (108x108) seukuran size di posisi x, y, latar transparan."""
    fg = re.sub(r'^<svg[^>]*>|</svg>$', '', DRAWABLES['ic_launcher_foreground'][1])
    return (f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}">'
            f'<g transform="translate({x} {y}) scale({size / 108})">{fg}</g></svg>')


def gradient(w, h, start, end, diagonal):
    """Gradasi seperti ic_launcher_background (diagonal) atau bg_splash (atas ke bawah)."""
    from PIL import Image
    s = [int(start.lstrip('#')[i:i + 2], 16) for i in (0, 2, 4)]
    e = [int(end.lstrip('#')[i:i + 2], 16) for i in (0, 2, 4)]
    small = Image.new('RGB', (64, 64))
    for yy in range(64):
        for xx in range(64):
            t = (xx + yy) / 126 if diagonal else yy / 63
            small.putpixel((xx, yy), tuple(round(s[i] + (e[i] - s[i]) * t) for i in range(3)))
    return small.resize((w, h), Image.BILINEAR)


def compose(path, w, h, start, end, diagonal, logo, x, y):
    import io
    import fitz  # PyMuPDF
    from PIL import Image
    img = gradient(w, h, start, end, diagonal)
    doc = fitz.open(stream=fg_svg(w, h, logo, x, y).encode(), filetype='svg')
    fg = Image.open(io.BytesIO(doc[0].get_pixmap(alpha=True).tobytes('png'))).convert('RGBA')
    img.paste(fg, (0, 0), fg)
    img.save(path)


# Ukuran layar iPhone (poin x skala) untuk apple-touch-startup-image.
IPHONE_SCREENS = [(375, 667, 2), (414, 736, 3), (375, 812, 3), (414, 896, 2), (414, 896, 3), (390, 844, 3),
                  (428, 926, 3), (393, 852, 3), (430, 932, 3), (402, 874, 3), (440, 956, 3), (360, 780, 3)]


def make_icons():
    icons = OUT.parent / 'icons'
    icons.mkdir(exist_ok=True)
    light = read_colors(RES / 'values/colors.xml')
    for size in [180, 192, 512]:
        compose(str(icons / f'icon-{size}.png'), size, size, '#2453B5', '#0F2A5C', True, size, 0, 0)
    links = []
    for w, h, k in IPHONE_SCREENS:
        W, H = w * k, h * k
        name = f'splash-{W}x{H}.png'
        logo = round(min(W, H) * 0.55)
        compose(str(icons / name), W, H, light['primary'], light['splash_end'], False, logo, (W - logo) / 2, (H - logo) / 2)
        links.append(f'<link rel="apple-touch-startup-image" href="icons/{name}" media="(device-width: {w}px) and '
                     f'(device-height: {h}px) and (-webkit-device-pixel-ratio: {k}) and (orientation: portrait)">')
    # Tautan layar pembuka iPhone ditulis langsung ke index.html.
    index = OUT.parent / 'index.html'
    page = index.read_text(encoding='utf-8')
    block = '<!-- startup:begin -->\n' + '\n'.join(links) + '\n<!-- startup:end -->'
    page = re.sub(r'<!-- startup:begin -->.*<!-- startup:end -->', lambda _: block, page, flags=re.S)
    index.write_text(page, encoding='utf-8')


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / 'res.css').write_text('/* Dibuat otomatis oleh tools/android_to_web.py. Jangan diubah manual. */\n'
                                 + colors_css() + '\n' + styles_css() + '\n' + drawables_css() + '\n', encoding='utf-8')
    layouts = {}
    for f in sorted((RES / 'layout').glob('*.xml')):
        if f.stem == 'activity_main':
            continue
        layouts[f.stem] = element(ET.parse(f).getroot(), None, root=True)
    icons = {k: v for k, (kind, v) in DRAWABLES.items() if kind == 'vector'}
    shapes = [k for k, (kind, _) in DRAWABLES.items() if kind != 'vector']
    js = ('/* Dibuat otomatis oleh tools/android_to_web.py. Jangan diubah manual. */\n'
          f'window.LAYOUTS={json.dumps(layouts, ensure_ascii=False)};\n'
          f'window.ICONS={json.dumps(icons, ensure_ascii=False)};\n'
          f'window.SHAPES={json.dumps(shapes)};\n'
          f'window.STR={json.dumps(STRINGS, ensure_ascii=False)};\n')
    (OUT / 'res.js').write_text(js, encoding='utf-8')
    # Animasi Lottie dan huruf Lexend dipakai apa adanya.
    (OUT / 'raw').mkdir(exist_ok=True)
    for f in (RES / 'raw').glob('*.json'):
        shutil.copy(f, OUT / 'raw' / f.name)
    make_icons()
    print(f'{len(layouts)} layout, {len(icons)} ikon, {len(STRINGS)} teks -> {OUT}')


if __name__ == '__main__':
    main()
