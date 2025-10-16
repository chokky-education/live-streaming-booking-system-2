<?php
/**
 * Landing page with Finpay-inspired experience
 */

require_once __DIR__ . '/includes/layout.php';

render_page_start('ระบบจองอุปกรณ์ Live Streaming', [
    'active' => 'home',
]);
?>

<section class="hero">
    <div class="page-container hero__inner">
        <div>
            <div class="hero__eyebrow">Live Streaming Finance Tools</div>
            <h1 class="hero__title">รับเงินก่อนเวลา จัดการงานสตรีมได้ครบทุกขั้นตอน</h1>
            <p class="hero__subtitle">
                แพลตฟอร์มที่ช่วยธุรกิจไลฟ์สตรีมมิ่งจัดการการจองอุปกรณ์ การออกใบแจ้งหนี้ และการชำระเงิน
                พร้อมรายงานแบบเรียลไทม์ในที่เดียว
            </p>
            <div class="hero__actions">
                <a class="btn btn-primary" href="/register">เปิดบัญชีทันที</a>
                <a class="btn btn-ghost" href="/booking">ดูแพ็คเกจ</a>
            </div>
            <div class="hero__partners">
                <span>เชื่อมต่อกับ</span>
                <span>Klarna</span>
                <span>Coinbase</span>
                <span>Instacart</span>
            </div>
        </div>
    </div>
</section>

<section class="section" id="how-it-works">
    <div class="page-container">
        <div class="section-header">
            <h2>ประสบการณ์ที่เติบโตไปพร้อมธุรกิจของคุณ</h2>
            <p>สร้างประสบการณ์การทำงานทางการเงินที่ปลอดภัย ด้วยระบบจองที่จัดการง่าย เชื่อมต่อการชำระเงิน
                พร้อมแดชบอร์ดรายงานที่ออกแบบมาเพื่อทีมสตรีมมิ่ง</p>
        </div>

        <div class="card-grid">
            <div class="feature-card">
                <i class="fa-solid fa-arrow-trend-up" style="font-size:1.8rem; color:var(--brand-primary);"></i>
                <h3>โอนเข้าบัญชีทันที</h3>
                <p>ส่งใบแจ้งหนี้และรับชำระเงินพร้อมปรับยอดแบบเรียลไทม์ ช่วยให้ทีมสตรีมมิ่งหมุนเงินได้ไวกว่าเดิม</p>
            </div>
            <div class="feature-card">
                <i class="fa-solid fa-diagram-project" style="font-size:1.8rem; color:var(--brand-primary);"></i>
                <h3>บริหารหลายโปรเจกต์</h3>
                <p>จัดการหลายอีเวนท์พร้อมกัน ดูสถานะ การจอง และการชำระเงินได้ในหน้าจอเดียว</p>
            </div>
            <div class="feature-card">
                <i class="fa-solid fa-shield-check" style="font-size:1.8rem; color:var(--brand-primary);"></i>
                <h3>ความปลอดภัยครบ</h3>
                <p>ควบคุมสิทธิ์ผู้ใช้พร้อมบันทึกกิจกรรม ตรวจสอบย้อนกลับได้ทุกการเคลื่อนไหวอย่างละเอียด</p>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background:white;">
    <div class="page-container">
        <div class="section-header">
            <h2>ทำไมธุรกิจจึงเลือกเรา</h2>
            <p>เชื่อมต่อโครงสร้างการจอง ชำระ และการดูแลลูกค้าไว้ในศูนย์กลางเดียว</p>
        </div>

        <div class="card-grid">
            <div class="stat-card">
                <div class="stat-card__number">3k+</div>
                <p>สตูดิโอไลฟ์สตรีมมิ่งกำลังรันงานด้วยแพลตฟอร์มนี้</p>
            </div>
            <div class="stat-card">
                <div class="stat-card__number">24%</div>
                <p>เพิ่มอัตราหมุนเวียนรายได้หลังเปิดใช้ระบบบริหารใบจอง</p>
            </div>
            <div class="stat-card">
                <div class="stat-card__number">180k</div>
                <p>ยอดเงินเฉลี่ยที่สถานีสามารถจัดการต่อเดือนผ่านระบบของเรา</p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="page-container">
        <div class="glass-card" style="display:grid; gap:24px;">
            <div>
                <h3>สร้างผลตอบแทนสูงสุดจากทุกอีเวนท์</h3>
                <p style="margin:0;">กำหนดขั้นตอนการรับงานให้ทีมทำงานต่อเนื่อง ตั้งแต่รับจองจนจบการชำระเงิน</p>
            </div>
            <div class="card-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                <div>
                    <strong>1</strong>
                    <p>เปิดโปรเจกต์และตั้งค่าอุปกรณ์ที่พร้อมให้จอง</p>
                </div>
                <div>
                    <strong>2</strong>
                    <p>ย้ายงานระหว่างทีมและติดตามความคืบหน้าในหน้าเดียว</p>
                </div>
                <div>
                    <strong>3</strong>
                    <p>ให้ระบบแจ้งเตือนทีมเรื่องการส่งคืนและการชำระเงินอัตโนมัติ</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background:white;">
    <div class="page-container">
        <div class="section-header">
            <h2>แพ็คเกจที่ยืดหยุ่นตามสเกล</h2>
            <p>เลือกแพ็คเกจที่เหมาะกับจังหวะธุรกิจของคุณ จ่ายตามการใช้งานจริง พร้อมอัปเกรดได้ตลอดเวลา</p>
        </div>
        <div class="card-grid">
            <div class="tier-card">
                <div class="tier-card__label">Plus</div>
                <p class="tier-card__price">฿2,900 / เดือน</p>
                <ul style="list-style:none; padding:0; margin:18px 0 24px; display:grid; gap:12px;">
                    <li><i class="fa-solid fa-check" style="color:var(--brand-primary);"></i> จัดการอุปกรณ์ได้ 2 โปรเจกต์</li>
                    <li><i class="fa-solid fa-check" style="color:var(--brand-primary);"></i> แจ้งเตือนกำหนดส่งคืน</li>
                    <li><i class="fa-solid fa-check" style="color:var(--brand-primary);"></i> รายงานการเงินรายเดือน</li>
                </ul>
                <a class="btn btn-ghost" href="/register">เริ่มทดลอง</a>
            </div>
            <div class="tier-card" style="background:var(--brand-gradient); color:white;">
                <div class="tier-card__label" style="color:rgba(255,255,255,0.7);">Premium</div>
                <p class="tier-card__price">฿6,900 / เดือน</p>
                <ul style="list-style:none; padding:0; margin:18px 0 24px; display:grid; gap:12px;">
                    <li><i class="fa-solid fa-check"></i> บริหารอุปกรณ์ไม่จำกัดจำนวน</li>
                    <li><i class="fa-solid fa-check"></i> Workflow automation เต็มรูปแบบ</li>
                    <li><i class="fa-solid fa-check"></i> ที่ปรึกษาด้านการเงินเฉพาะทีม</li>
                </ul>
                <a class="btn btn-primary" href="/booking" style="background:white; color:var(--brand-secondary);">จองเดี๋ยวนี้</a>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="page-container" style="display:grid; gap:32px;">
        <div class="glass-card glass-card--cta">
            <div style="display:flex; flex-direction:column; gap:18px;">
                <h3>พร้อมยกระดับการจัดการการชำระเงินของคุณหรือยัง?</h3>
                <p style="margin:0; opacity:0.8;">เปิดใช้งานแพลตฟอร์มวันนี้เพื่อเริ่มต้นรับเงินล่วงหน้าและบริหารการจองได้ครบทุกมิติ</p>
                <div class="hero__actions">
                    <a class="btn btn-primary" href="/register">เริ่มต้นทันที</a>
                    <a class="btn btn-ghost btn-ghost--cta" href="mailto:support@example.com">คุยกับผู้เชี่ยวชาญ</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
render_page_end();
?>
