// assets/js/charts.js

class LineChart {
    constructor(canvasId, options) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        this.ctx = this.canvas.getContext('2d');
        this.data = options.data || [];
        this.labels = options.labels || [];
        this.colors = {
            primary: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#0d631b',
            surface: getComputedStyle(document.documentElement).getPropertyValue('--surface').trim() || '#f9f9f9',
            grid: 'rgba(26, 28, 28, 0.05)'
        };
        
        // Handle resize
        window.addEventListener('resize', () => this.resize());
        this.resize();
    }

    resize() {
        const parent = this.canvas.parentElement;
        this.canvas.width = parent.clientWidth;
        this.canvas.height = parent.clientHeight;
        this.draw();
    }

    draw() {
        if (this.data.length === 0) return;
        
        const width = this.canvas.width;
        const height = this.canvas.height;
        const ctx = this.ctx;
        
        ctx.clearRect(0, 0, width, height);

        const padding = 20;
        const graphWidth = width - padding * 2;
        const graphHeight = height - padding * 2;

        const maxVal = Math.max(...this.data, 1);
        const minVal = 0;

        // Draw grid
        ctx.beginPath();
        ctx.strokeStyle = this.colors.grid;
        ctx.lineWidth = 1;
        for (let i = 0; i <= 4; i++) {
            const y = padding + (graphHeight * i) / 4;
            ctx.moveTo(padding, y);
            ctx.lineTo(width - padding, y);
        }
        ctx.stroke();

        // Calculate points
        const points = this.data.map((val, index) => {
            const x = padding + (index * (graphWidth / (this.data.length - 1 || 1)));
            const y = height - padding - ((val / maxVal) * graphHeight);
            return {x, y};
        });

        // Draw Area Gradient
        ctx.beginPath();
        ctx.moveTo(points[0].x, height - padding);
        points.forEach(p => ctx.lineTo(p.x, p.y));
        ctx.lineTo(points[points.length - 1].x, height - padding);
        ctx.closePath();

        const gradient = ctx.createLinearGradient(0, padding, 0, height - padding);
        gradient.addColorStop(0, `${this.colors.primary}40`); // 25% opacity
        gradient.addColorStop(1, `${this.colors.primary}00`); // 0% opacity
        ctx.fillStyle = gradient;
        ctx.fill();

        // Draw Line (3px stroke as per design)
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        points.forEach(p => ctx.lineTo(p.x, p.y));
        ctx.strokeStyle = this.colors.primary;
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.stroke();

        // Draw Points
        points.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, 4, 0, 2 * Math.PI);
            ctx.fillStyle = '#fff';
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = this.colors.primary;
            ctx.stroke();
        });
    }
}

// Initialize on Dashboard
document.addEventListener('DOMContentLoaded', () => {
    const trendCanvas = document.getElementById('trendChart');
    if (trendCanvas) {
        fetch('api/get_chart_data.php')
            .then(res => res.json())
            .then(data => {
                new LineChart('trendChart', {
                    data: data.data,
                    labels: data.labels
                });
            })
            .catch(err => console.error("Error fetching chart data", err));
    }
});
