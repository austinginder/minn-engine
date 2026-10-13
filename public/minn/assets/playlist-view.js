/*! Minn Engine playlist block view | MIT | The core/playlist store the block's markup calls into, and its waveform player. */
import { store, getContext, getElement } from '@wordpress/interactivity';

const SVG = 'http://www.w3.org/2000/svg';
const ICONS = { play: 'M8 5v14l11-7z', pause: 'M6 5h4v14H6zm8 0h4v14h-4z' };

// m:ss for a number of seconds.
function clock(seconds) {
	const s = Math.max(0, Math.floor(seconds || 0));
	return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

function element(tag, className, attributes = {}) {
	const node = document.createElement(tag);
	if (className) node.className = className;
	for (const [name, value] of Object.entries(attributes)) node.setAttribute(name, value);
	return node;
}

function icon(name) {
	const svg = document.createElementNS(SVG, 'svg');
	svg.setAttribute('viewBox', '0 0 24 24');
	svg.setAttribute('aria-hidden', 'true');
	svg.setAttribute('focusable', 'false');
	const path = document.createElementNS(SVG, 'path');
	path.setAttribute('d', ICONS[name]);
	svg.append(path);
	const span = element('span', `waveform-icon-${name}`);
	span.append(svg);
	return span;
}

// The text colour of the block, at an opacity, for the bars.
function shade(colour, alpha) {
	const match = /rgba?\(([^)]+)\)/.exec(colour || '');
	const [r, g, b] = match ? match[1].split(',').map((part) => parseFloat(part)) : [17, 17, 17];
	return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

// Bar heights (0 to 1) for an audio file, from its decoded samples; an even row when it cannot be read.
const peaksCache = new Map();
function peaks(url, count) {
	const key = `${url}|${count}`;
	if (!peaksCache.has(key)) {
		peaksCache.set(key, fetch(url)
			.then((response) => response.arrayBuffer())
			.then((data) => new (window.AudioContext || window.webkitAudioContext)().decodeAudioData(data))
			.then((audio) => {
				const samples = audio.getChannelData(0);
				const size = Math.max(1, Math.floor(samples.length / count));
				const out = [];
				for (let i = 0; i < count; i++) {
					let max = 0;
					for (let j = i * size; j < Math.min(samples.length, (i + 1) * size); j += 16) max = Math.max(max, Math.abs(samples[j]));
					out.push(max);
				}
				const top = Math.max(...out, 0.0001);
				return out.map((value) => Math.max(0.05, value / top));
			})
			.catch(() => new Array(count).fill(0.35)));
	}
	return peaksCache.get(key);
}

// One player per playlist: built once into its holder, then told which track to show and whether it plays.
class Player {
	constructor(holder, labels, onToggle, onEnded) {
		this.labels = labels;
		this.audio = new Audio();
		this.audio.preload = 'metadata';
		this.url = '';
		this.bars = [];
		const colour = getComputedStyle(holder).color;
		this.colours = { wave: shade(colour, 0.3), progress: shade(colour, 0.6) };
		this.root = element('div', 'waveform-player waveform-theme-light', { 'data-waveform-player': '', tabindex: '-1' });
		const inner = element('div', 'waveform-player-inner');
		const body = element('div', 'waveform-body');
		const track = element('div', 'waveform-track waveform-align-bottom');
		this.button = element('button', 'waveform-btn', { type: 'button', 'aria-label': labels.play });
		this.button.append(icon('play'), icon('pause'));
		this.slider = element('div', 'waveform-container', { role: 'slider', tabindex: '0', 'aria-valuemin': '0', 'aria-valuemax': '0', 'aria-valuenow': '0', style: 'height: 100px;' });
		this.canvas = element('canvas');
		this.slider.append(this.canvas, element('div', 'waveform-markers'), element('div', 'waveform-loading'), element('div', 'waveform-error', { role: 'alert', style: 'display:none;' }), element('div', 'waveform-hover-time', { 'aria-hidden': 'true' }));
		track.append(this.button, this.slider);
		const info = element('div', 'waveform-info');
		const text = element('div', 'waveform-text');
		this.title = element('span', 'waveform-title');
		this.artist = element('span', 'waveform-artist');
		text.append(this.title, this.artist);
		const meta = element('div', 'waveform-meta');
		this.time = element('span', 'waveform-time');
		meta.append(this.time);
		info.append(text, meta);
		body.append(track, info);
		inner.append(body);
		this.root.append(inner);
		holder.append(this.root);
		this.button.addEventListener('click', () => onToggle());
		this.audio.addEventListener('timeupdate', () => this.progress());
		this.audio.addEventListener('loadedmetadata', () => this.progress());
		this.audio.addEventListener('ended', () => onEnded());
		this.slider.addEventListener('click', (event) => {
			const box = this.slider.getBoundingClientRect();
			this.seek(((event.clientX - box.left) / box.width) * (this.audio.duration || 0));
		});
		this.slider.addEventListener('keydown', (event) => {
			const to = { ArrowRight: this.audio.currentTime + 5, ArrowLeft: this.audio.currentTime - 5, Home: 0, End: this.audio.duration || 0 }[event.key];
			if (to === undefined) return;
			event.preventDefault();
			this.seek(to);
		});
		window.addEventListener('resize', () => this.draw());
	}

	show(track) {
		if (track.url === this.url) return;
		this.url = track.url;
		this.audio.src = track.url;
		this.title.textContent = track.title || '';
		this.artist.textContent = track.artist || '';
		this.slider.setAttribute('aria-label', track.title || this.labels.seek);
		this.bars = [];
		this.draw();
		const count = Math.max(20, Math.floor(this.slider.clientWidth / 4));
		peaks(track.url, count).then((bars) => {
			if (this.url === track.url) {
				this.bars = bars;
				this.draw();
			}
		});
		this.progress();
	}

	play(playing) {
		this.button.classList.toggle('playing', playing);
		this.button.setAttribute('aria-label', playing ? this.labels.pause : this.labels.play);
		this.button.querySelector('.waveform-icon-play').style.display = playing ? 'none' : 'flex';
		this.button.querySelector('.waveform-icon-pause').style.display = playing ? 'flex' : 'none';
		if (playing && this.audio.paused) this.audio.play().catch(() => {});
		if (!playing && !this.audio.paused) this.audio.pause();
	}

	seek(seconds) {
		if (!Number.isFinite(this.audio.duration)) return;
		this.audio.currentTime = Math.min(this.audio.duration, Math.max(0, seconds));
		this.progress();
	}

	progress() {
		const duration = Number.isFinite(this.audio.duration) ? this.audio.duration : 0;
		const now = this.audio.currentTime || 0;
		this.slider.setAttribute('aria-valuemax', String(Math.round(duration)));
		this.slider.setAttribute('aria-valuenow', String(Math.round(now)));
		this.slider.setAttribute('aria-valuetext', this.labels.seekValue.replace('%1$s', clock(now)).replace('%2$s', clock(duration)));
		this.time.textContent = `${clock(now)} / ${clock(duration)}`;
		this.draw();
	}

	draw() {
		const width = this.slider.clientWidth;
		const height = this.slider.clientHeight || 100;
		if (!width) return;
		const ratio = window.devicePixelRatio || 1;
		this.canvas.width = width * ratio;
		this.canvas.height = height * ratio;
		this.canvas.style.width = `${width}px`;
		this.canvas.style.height = `${height}px`;
		const context = this.canvas.getContext('2d');
		context.scale(ratio, ratio);
		context.clearRect(0, 0, width, height);
		const bars = this.bars.length ? this.bars : new Array(Math.max(20, Math.floor(width / 4))).fill(0.08);
		const step = width / bars.length;
		const played = this.audio.duration ? this.audio.currentTime / this.audio.duration : 0;
		bars.forEach((value, index) => {
			context.fillStyle = index / bars.length < played ? this.colours.progress : this.colours.wave;
			const bar = Math.max(1, value * height);
			context.fillRect(index * step, height - bar, Math.max(1, step - 1), bar);
		});
	}
}

const players = new WeakMap();

const { state } = store('core/playlist', {
	state: {
		get isCurrentTrack() {
			const ctx = getContext();
			return ctx.trackId === ctx.currentId;
		},
		get trackButtonActionLabel() {
			const ctx = getContext();
			return ctx.trackId === ctx.currentId && ctx.isPlaying ? ctx.labelPauseTrack : ctx.labelSelectTrack;
		},
		get currentTrack() {
			const ctx = getContext();
			return state.playlists?.[ctx.playlistId]?.tracks?.[ctx.currentId] || null;
		},
	},
	actions: {
		// The playing track pauses or plays again; another one starts.
		changeTrack() {
			const ctx = getContext();
			if (ctx.trackId === ctx.currentId) {
				ctx.isPlaying = !ctx.isPlaying;
			} else {
				ctx.currentId = ctx.trackId;
				ctx.isPlaying = true;
			}
		},
	},
	callbacks: {
		initWaveformPlayer() {
			const { ref } = getElement();
			const ctx = getContext();
			let player = players.get(ref);
			if (!player) {
				const labels = { play: ref.dataset.labelPlay || 'Play', pause: ref.dataset.labelPause || 'Pause', seek: ref.dataset.labelSeek || 'Seek', seekValue: ref.dataset.labelSeekValue || '%1$s of %2$s' };
				const scoped = (run) => () => run(ctx);
				player = new Player(ref, labels, scoped((c) => { c.isPlaying = !c.isPlaying; }), scoped((c) => {
					const at = c.tracks.indexOf(c.currentId);
					if (at >= 0 && at < c.tracks.length - 1) {
						c.currentId = c.tracks[at + 1];
					} else {
						c.isPlaying = false;
					}
				}));
				players.set(ref, player);
			}
			const track = state.currentTrack;
			if (track) player.show(track);
			player.play(!!ctx.isPlaying);
		},
	},
});
