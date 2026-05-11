<?php
// pages/cashier.php
require_once __DIR__ . '/../includes/config.php';
requireLogin(['Admin','Cashier']);
$pageTitle = 'POS — Cashier';
$db = getDB();

// Handle checkout POST
$msg = ''; $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cartJson      = $_POST['cart_json'] ?? '[]';
    $customerName  = trim($_POST['customer_name'] ?? 'Walk-in Customer') ?: 'Walk-in Customer';
    $customerPhone = trim($_POST['customer_phone'] ?? '') ?: null;
    $payMethod     = $_POST['payment_method'] ?? 'Cash';
    $tendered      = floatval($_POST['amount_tendered'] ?? 0);
    $discount      = floatval($_POST['discount'] ?? 0);
    $cart          = json_decode($cartJson, true) ?? [];

    if (empty($cart)) {
        $msg = 'Cart is empty.'; $msgType = 'danger';
    } else {
        try {
            $db->beginTransaction();

            // Calculate total
            $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
            $total    = round($subtotal * (1 - $discount/100), 2);

            // Insert transaction
            $stmt = $db->prepare('INSERT INTO Transaction (CustomerName,CustomerPhone,PaymentMethod,AmountTendered,Discount,TotalAmount,Status,Cashier_ID) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$customerName,$customerPhone,$payMethod,$tendered,$discount,$total,'Completed',$_SESSION['user_id']]);
            $txnId = $db->lastInsertId();

            // Insert sale items
            $siStmt = $db->prepare('INSERT INTO SaleItem (Quantity,UnitPrice,Product_ID,Transaction_ID) VALUES (?,?,?,?)');
            foreach ($cart as $item) {
                $siStmt->execute([$item['qty'],$item['price'],$item['product_id'],$txnId]);
            }

            $db->commit();
            $msg = "Transaction #TXN-".str_pad($txnId,5,'0',STR_PAD_LEFT)." completed! Change: ₱".number_format(max(0,$tendered-$total),2);
            $msgType = 'success';
            $lastTxnId = $txnId;
        } catch (Exception $e) {
            $db->rollBack();
            $msg = 'Error: ' . $e->getMessage(); $msgType = 'danger';
        }
    }
}

// Load products with stock
$products = $db->query("
    SELECT p.ID, p.Name, p.Brand, p.Price, c.Name AS CategoryName,
           COALESCE(SUM(s.Quantity),0) AS StockQty
    FROM Product p
    LEFT JOIN Category c ON p.Category_ID = c.ID
    LEFT JOIN Stock s ON s.Product_ID = p.ID
    GROUP BY p.ID
    ORDER BY c.Name, p.Name
")->fetchAll();

// Group by category
$byCategory = [];
foreach ($products as $p) { $byCategory[$p['CategoryName']][] = $p; }

// Categories for filter
$categories = array_keys($byCategory);

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="pos-layout">

  <!-- Left: Product Browser -->
  <div class="pos-products">
    <div class="toolbar" style="position:sticky;top:0;background:var(--bg);padding-bottom:10px;z-index:10">
      <div class="search-box">
        <span>🔍</span>
        <input type="text" id="product-search" placeholder="Search products…" oninput="filterProducts()">
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <button class="btn btn-ghost btn-sm active-filter" onclick="filterCat('all',this)">All</button>
        <?php foreach($categories as $cat): ?>
        <button class="btn btn-ghost btn-sm" onclick="filterCat('<?= htmlspecialchars(addslashes($cat)) ?>',this)">
          <?= htmlspecialchars($cat) ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <?php foreach($byCategory as $catName => $prods): ?>
    <div class="cat-group" data-category="<?= htmlspecialchars($catName) ?>">
      <h3 style="font-family:'Syne',sans-serif;font-size:12px;font-weight:700;color:var(--text-muted);letter-spacing:1px;text-transform:uppercase;margin:16px 0 8px"><?= htmlspecialchars($catName) ?></h3>
      <div class="product-grid">
        <?php foreach($prods as $p): ?>
        <div class="product-tile <?= $p['StockQty']<=0?'out-of-stock':'' ?>"
             data-id="<?= $p['ID'] ?>"
             data-name="<?= htmlspecialchars(addslashes($p['Name'])) ?>"
             data-brand="<?= htmlspecialchars(addslashes($p['Brand'])) ?>"
             data-price="<?= $p['Price'] ?>"
             data-stock="<?= $p['StockQty'] ?>"
             data-category="<?= htmlspecialchars($p['CategoryName']) ?>"
             onclick="addToCart(this)">
          <div class="product-tile-brand"><?= htmlspecialchars($p['Brand']) ?> · <?= htmlspecialchars($catName) ?></div>
          <div class="product-tile-name"><?= htmlspecialchars($p['Name']) ?></div>
          <div class="product-tile-price">₱<?= number_format($p['Price'],2) ?></div>
          <div class="product-tile-stock <?= $p['StockQty']<=0?'out-of-stock-text':($p['StockQty']<=5?'low-stock':'') ?>">
            <?= $p['StockQty']<=0 ? 'Out of Stock' : 'Stock: '.$p['StockQty'] ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Right: Cart -->
  <div class="pos-cart">
    <div class="cart-header">🛒 Cart <span id="cart-count" style="color:var(--accent)">(0 items)</span></div>
    <div class="cart-items" id="cart-items">
      <div class="empty-state" id="cart-empty">
        <div class="empty-icon">🛒</div>
        <p>No items yet.<br>Click a product to add.</p>
      </div>
    </div>
    <div class="cart-footer">
      <div class="cart-totals">
        <table>
          <tr><td style="color:var(--text-muted)">Subtotal</td><td id="cart-subtotal">₱0.00</td></tr>
          <tr>
            <td style="color:var(--text-muted)">Discount</td>
            <td><input type="number" id="discount-input" min="0" max="100" value="0" step="1"
                        style="width:60px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:3px 7px;text-align:right" oninput="recalc()">%</td>
          </tr>
          <tr class="total-row"><td>TOTAL</td><td id="cart-total">₱0.00</td></tr>
        </table>
      </div>
      <div class="cart-actions">
        <button class="btn btn-primary" onclick="openCheckoutModal()" style="justify-content:center;padding:12px">
          Proceed to Checkout →
        </button>
        <button class="btn btn-ghost btn-sm" onclick="clearCart()" style="justify-content:center">Clear Cart</button>
      </div>
    </div>
  </div>

</div>

<!-- Checkout Modal -->
<div class="modal-overlay" id="checkout-modal">
  <div class="modal" style="width:min(480px,95vw)">
    <div class="modal-header">
      <span class="modal-title">💳 Checkout</span>
      <button class="modal-close" onclick="closeModal('checkout-modal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="checkout-form">
        <input type="hidden" name="checkout" value="1">
        <input type="hidden" name="cart_json" id="cart-json-input">
        <input type="hidden" name="discount" id="discount-hidden">

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Customer Name</label>
            <input type="text" name="customer_name" class="form-control" placeholder="Walk-in Customer">
          </div>
          <div class="form-group">
            <label class="form-label">Phone (optional)</label>
            <input type="text" name="customer_phone" class="form-control" placeholder="09XXXXXXXXX">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Payment Method</label>
          <select name="payment_method" class="form-control" onchange="toggleTendered(this.value)">
            <option value="Cash">💵 Cash</option>
            <option value="Card">💳 Card</option>
            <option value="GCash">📱 GCash</option>
            <option value="PayMaya">📱 PayMaya</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div class="form-group" id="tendered-group">
          <label class="form-label">Amount Tendered</label>
          <input type="number" name="amount_tendered" id="tendered-amount" class="form-control" step="0.01" min="0" placeholder="0.00" oninput="calcChange()">
        </div>

        <div style="background:var(--surface2);border-radius:10px;padding:14px;margin-bottom:16px">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px">
            <span style="color:var(--text-muted)">Subtotal</span><span id="modal-subtotal">₱0.00</span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px">
            <span style="color:var(--text-muted)">Discount</span><span id="modal-discount">0%</span>
          </div>
          <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding-top:8px;margin-top:4px">
            <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:16px">TOTAL</span>
            <span id="modal-total" style="font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--accent)">₱0.00</span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:8px;color:var(--accent3)" id="change-row">
            <span>Change</span><span id="modal-change">₱0.00</span>
          </div>
        </div>

        <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;padding:12px;font-size:15px">
          ✓ Confirm Transaction
        </button>
      </form>
    </div>
  </div>
</div>

<script>
// Cart state
let cart = {};

function addToCart(el) {
  const id    = el.dataset.id;
  const stock = parseInt(el.dataset.stock);
  if (stock <= 0) return;
  if (!cart[id]) {
    cart[id] = { product_id:parseInt(id), name:el.dataset.name, brand:el.dataset.brand,
                 price:parseFloat(el.dataset.price), qty:0, maxStock:stock };
  }
  if (cart[id].qty >= stock) { alert('Cannot add more than available stock ('+stock+')'); return; }
  cart[id].qty++;
  renderCart();
}

function renderCart() {
  const container = document.getElementById('cart-items');
  const emptyMsg  = document.getElementById('cart-empty');
  const keys = Object.keys(cart).filter(k => cart[k].qty > 0);

  document.getElementById('cart-count').textContent = `(${keys.reduce((a,k)=>a+cart[k].qty,0)} items)`;

  if (keys.length === 0) {
    container.innerHTML = '';
    container.appendChild(emptyMsg);
    emptyMsg.style.display = 'block';
    recalc(); return;
  }
  emptyMsg.style.display = 'none';
  container.innerHTML = keys.map(k => {
    const i = cart[k];
    return `<div class="cart-item">
      <div>
        <div class="cart-item-name">${i.name}</div>
        <div style="font-size:11px;color:var(--text-muted)">${i.brand}</div>
      </div>
      <div class="cart-item-qty">
        <button class="qty-btn" onclick="changeQty('${k}',-1)">−</button>
        <span style="min-width:20px;text-align:center;font-weight:600">${i.qty}</span>
        <button class="qty-btn" onclick="changeQty('${k}',1)">+</button>
      </div>
      <div class="cart-item-price">₱${(i.price*i.qty).toLocaleString('en-PH',{minimumFractionDigits:2})}</div>
      <button class="cart-item-remove" onclick="removeItem('${k}')">✕</button>
    </div>`;
  }).join('');
  recalc();
}

function changeQty(id, delta) {
  if (!cart[id]) return;
  cart[id].qty = Math.max(0, Math.min(cart[id].maxStock, cart[id].qty + delta));
  if (cart[id].qty === 0) delete cart[id];
  renderCart();
}
function removeItem(id) { delete cart[id]; renderCart(); }
function clearCart()    { cart = {}; renderCart(); }

function recalc() {
  const sub = Object.values(cart).reduce((a,i)=>a+(i.price*i.qty),0);
  const disc= parseFloat(document.getElementById('discount-input').value) || 0;
  const total = sub * (1 - disc/100);
  document.getElementById('cart-subtotal').textContent = '₱'+sub.toLocaleString('en-PH',{minimumFractionDigits:2});
  document.getElementById('cart-total').textContent    = '₱'+total.toLocaleString('en-PH',{minimumFractionDigits:2});
}

function openCheckoutModal() {
  const keys = Object.keys(cart).filter(k => cart[k].qty > 0);
  if (keys.length === 0) { alert('Cart is empty!'); return; }
  const disc = parseFloat(document.getElementById('discount-input').value)||0;
  const sub  = Object.values(cart).reduce((a,i)=>a+(i.price*i.qty),0);
  const total = sub*(1-disc/100);
  document.getElementById('cart-json-input').value = JSON.stringify(keys.map(k=>cart[k]));
  document.getElementById('discount-hidden').value = disc;
  document.getElementById('modal-subtotal').textContent = '₱'+sub.toLocaleString('en-PH',{minimumFractionDigits:2});
  document.getElementById('modal-discount').textContent = disc+'%';
  document.getElementById('modal-total').textContent    = '₱'+total.toLocaleString('en-PH',{minimumFractionDigits:2});
  calcChange();
  openModal('checkout-modal');
}

function calcChange() {
  const disc = parseFloat(document.getElementById('discount-hidden').value)||0;
  const sub  = Object.values(cart).reduce((a,i)=>a+(i.price*i.qty),0);
  const total = sub*(1-disc/100);
  const tendered = parseFloat(document.getElementById('tendered-amount').value)||0;
  const change = Math.max(0, tendered-total);
  document.getElementById('modal-change').textContent = '₱'+change.toLocaleString('en-PH',{minimumFractionDigits:2});
}

function toggleTendered(method) {
  document.getElementById('tendered-group').style.display = method==='Cash'?'block':'none';
}

function filterCat(cat, btn) {
  document.querySelectorAll('.active-filter').forEach(b=>b.classList.remove('active-filter'));
  btn.classList.add('active-filter');
  document.querySelectorAll('.cat-group').forEach(g=>{
    g.style.display = (cat==='all' || g.dataset.category===cat) ? 'block' : 'none';
  });
  filterProducts();
}

function filterProducts() {
  const q = document.getElementById('product-search').value.toLowerCase();
  document.querySelectorAll('.product-tile').forEach(t=>{
    const match = t.dataset.name.toLowerCase().includes(q) || t.dataset.brand.toLowerCase().includes(q);
    t.style.display = match ? '' : 'none';
  });
}

renderCart();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
