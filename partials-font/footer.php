<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div><a class="brand footer-brand" href="<?= e(url()) ?>"><span>Velora.FOOD</span></a>
                <p>Fresh food, transparent checkout and simple order tracking in one modern restaurant ordering experience.</p>
            </div>
            <div>
                <h3>Explore</h3><a href="<?= e(url('foods.php')) ?>">Food menu</a><a href="<?= e(url('categories.php')) ?>">Categories</a><a href="<?= e(url('foods.php?sort=rating')) ?>">Top rated</a>
            </div>
            <div>
                <h3>Customer</h3><a href="<?= e(url('cart.php')) ?>">Cart</a><a href="<?= e(url('wishlist.php')) ?>">Wishlist</a><a href="<?= e(url('my_order.php')) ?>">My orders</a><a href="<?= e(url('profile.php')) ?>">Profile</a>
            </div>
            <div>
                <h3>Payments</h3>
                <p>Cash on Delivery<?php if (mobile_payment_enabled('bkash')): ?><br>bKash verification<?php endif; ?><?php if (mobile_payment_enabled('nagad')): ?><br>Nagad verification<?php endif; ?><?php if (sslcommerz_enabled()): ?><br>Secure online gateway<?php endif; ?></p>
            </div>
        </div>
        <div class="copyright">© <?= date('Y') ?> Velora Food • Restaurant Ordering System V3.1</div>
    </div>
</footer>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>

</html>