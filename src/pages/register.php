<?php
require_once '../includes/config.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $username = cleanInput($_POST['username']);
        $email = cleanInput($_POST['email']);
        $birthdate = cleanInput($_POST['birthdate']);
        $password = cleanInput($_POST['password']);
        $confirm_password = cleanInput($_POST['confirm_password']);
        $type = isset($_GET['parent_id']) ? 'child' : cleanInput($_POST['type']);

        // التحقق من كلمة المرور
        if($password !== $confirm_password) {
            throw new Exception("كلمة المرور غير متطابقة");
        }

        // حساب العمر
        $today = new DateTime();
        $birth = new DateTime($birthdate);
        $age = $birth->diff($today)->y;

        // التحقق من العمر
// في جزء التحقق من العمر في PHP
// التحقق من العمر
if($type == 'child') {
    if($age < 10) {
        throw new Exception("العمر يجب أن يكون 10 سنوات على الأقل");
    } else if($age > 18) {
        throw new Exception("العمر يجب أن يكون أقل من 18 سنة للأطفال");
    }
} else if($type == 'parent' && $age < 21) {
    throw new Exception("يجب أن يكون عمر ولي الأمر 21 سنة على الأقل");
}


        // التحقق من البريد الإلكتروني
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if($stmt->rowCount() > 0) {
            throw new Exception("البريد الإلكتروني مستخدم بالفعل");
        }

        // معالجة الصورة الشخصية
        $profile_image = null;
        if(isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if(!in_array($ext, $allowed)) {
                throw new Exception("نوع الملف غير مسموح به. الأنواع المسموحة: " . implode(', ', $allowed));
            }
            
            $profile_image = uniqid() . '.' . $ext;
            
            if (!file_exists(UPLOAD_PATH)) {
                mkdir(UPLOAD_PATH, 0777, true);
            }
            
            if(!move_uploaded_file($_FILES['profile_image']['tmp_name'], UPLOAD_PATH . $profile_image)) {
                throw new Exception("حدث خطأ أثناء رفع الصورة");
            }
        }

        // إنشاء الحساب
        $stmt = $conn->prepare("INSERT INTO users (username, email, birthdate, password, type, profile_image) VALUES (?, ?, ?, ?, ?, ?)");
        if($stmt->execute([$username, $email, $birthdate, $password, $type, $profile_image])) {
            $user_id = $conn->lastInsertId();

            // إذا كان طفل وتم إرساله من ولي أمر
            if($type == 'child' && isset($_GET['parent_id'])) {
                $parent_id = $_GET['parent_id'];
                $stmt = $conn->prepare("INSERT INTO parent_child (parent_id, child_id, status) VALUES (?, ?, 'approved')");
                $stmt->execute([$parent_id, $user_id]);
            }

            // تسجيل الدخول مباشرة
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['user_type'] = $type;
            $_SESSION['profile_image'] = $profile_image;

            header("Location: " . SITE_URL . "/pages/dashboard.php");
            exit;
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

require_once '../includes/header.php';
?>

<div class="register-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h2 class="text-warning mb-3">إنشاء حساب جديد</h2>
                            <?php if(isset($_GET['parent_id'])): ?>
                                <p class="text-muted">إنشاء حساب طفل مرتبط بولي الأمر</p>
                            <?php else: ?>
                                <p class="text-muted">مرحباً بك في منصة المبرمج الصغير</p>
                            <?php endif; ?>
                        </div>

                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <!-- إضافة الصورة الشخصية -->
                            <div class="text-center mb-4">
                                <div class="profile-upload">
                                    <img src="<?php echo SITE_URL; ?>/assets/images/default-avatar.png" 
                                         class="rounded-circle preview-image mb-3" 
                                         width="120" 
                                         height="120"
                                         alt="الصورة الشخصية">
                                    <div class="upload-btn-wrapper">
                                        <button class="btn btn-sm btn-outline-warning" type="button">
                                            <i class="fas fa-camera"></i>
                                            اختر صورة شخصية
                                        </button>
                                        <input type="file" 
                                               name="profile_image" 
                                               accept="image/*"
                                               onchange="previewImage(this)">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">اسم المستخدم</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="username" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">البريد الإلكتروني</label>
                                <input type="email" 
                                       class="form-control" 
                                       name="email" 
                                       required>
                            </div>
                            <div class="mb-3">
    <label class="form-label">تاريخ الميلاد</label>
    <input type="date"
           class="form-control"
           name="birthdate"
           required
           <?php if(isset($_GET['parent_id'])): ?>
               max="<?php echo date('Y-m-d', strtotime('-10 years')); ?>"
               min="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
           <?php else: ?>
               max="<?php echo date('Y-m-d', strtotime('-21 years')); ?>"
           <?php endif; ?>>
    <small class="text-muted age-helper">
        <?php if(isset($_GET['parent_id'])): ?>
            العمر المسموح به من 10 إلى 18 سنة
        <?php else: ?>
            يجب أن يكون عمرك 21 سنة على الأقل لولي الأمر
        <?php endif; ?>
    </small>
</div>




                            <div class="mb-3">
                                <label class="form-label">كلمة المرور</label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           name="password" 
                                           required>
                                    <button class="btn btn-outline-secondary" 
                                            type="button"
                                            onclick="togglePassword(this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">تأكيد كلمة المرور</label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           name="confirm_password" 
                                           required>
                                    <button class="btn btn-outline-secondary" 
                                            type="button"
                                            onclick="togglePassword(this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <?php if(!isset($_GET['parent_id'])): ?>
                                <div class="mb-4">
                                    <label class="form-label d-block">نوع الحساب</label>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" 
                                               class="form-check-input" 
                                               name="type" 
                                               value="parent" 
                                               id="type_parent" 
                                               checked>
                                        <label class="form-check-label" for="type_parent">
                                            ولي أمر
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" 
                                               class="form-check-input" 
                                               name="type" 
                                               value="child" 
                                               id="type_child">
                                        <label class="form-check-label" for="type_child">
                                            طفل
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning btn-lg">
                                    <i class="fas fa-user-plus"></i>
                                    إنشاء الحساب
                                </button>
                            </div>

                            <div class="text-center mt-4">
                                <p class="mb-0">
                                    لديك حساب بالفعل؟
                                    <a href="login.php" class="text-warning">
                                        تسجيل الدخول
                                    </a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.profile-upload {
    position: relative;
    display: inline-block;
}

.upload-btn-wrapper {
    position: relative;
    overflow: hidden;
    display: inline-block;
}

.upload-btn-wrapper input[type=file] {
    font-size: 100px;
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    cursor: pointer;
}

.preview-image {
    border: 3px solid #ffc107;
    padding: 3px;
    background-color: #fff;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // العناصر الرئيسية
    const form = document.querySelector('form');
    const birthdateInput = document.querySelector('input[name="birthdate"]');
    const typeInputs = document.querySelectorAll('input[name="type"]');
    const ageHelper = document.querySelector('.age-helper');
    const profileImage = document.querySelector('input[name="profile_image"]');

    // دالة معاينة الصورة قبل الرفع
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.querySelector('.preview-image').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // دالة إظهار/إخفاء كلمة المرور
    function togglePassword(button) {
        const input = button.parentElement.querySelector('input');
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // دالة حساب العمر
    function calculateAge(birthdate) {
        const today = new Date();
        const birthDate = new Date(birthdate);
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        return age;
    }

    // دالة تحديث رسالة العمر
    function updateAgeMessage(isParent) {
        if(isParent) {
            ageHelper.textContent = 'يجب أن يكون عمرك 21 سنة على الأقل لولي الأمر';
        } else {
            ageHelper.textContent = 'يجب أن يكون عمرك 13 سنة على الأقل';
        }
    }

    // دالة تحديث الحد الأقصى لتاريخ الميلاد
    function updateMaxDate(isParent) {
        const today = new Date();
        const minAge = isParent ? 21 : 13;
        const maxDate = new Date(today.getFullYear() - minAge, today.getMonth(), today.getDate());
        birthdateInput.max = maxDate.toISOString().split('T')[0];
    }

    // دالة التحقق من العمر
    function validateAge(birthdate, isParent) {
        const age = calculateAge(birthdate);
        
        if (isParent && age < 21) {
            alert('يجب أن يكون عمر ولي الأمر 21 سنة على الأقل');
            return false;
        } else if (!isParent && age < 13) {
            alert('يجب أن يكون عمرك 13 سنة على الأقل');
            return false;
        }
        
        return true;
    }

    // دالة التحقق من الصورة
    function validateImage(file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            alert('يجب أن تكون الصورة من نوع: JPG, PNG, GIF');
            return false;
        }

        if (file.size > maxSize) {
            alert('حجم الصورة يجب أن لا يتجاوز 5 ميجابايت');
            return false;
        }

        return true;
    }

    // تهيئة الصفحة
    const isParentAccount = document.getElementById('type_parent')?.checked ?? false;
    updateMaxDate(isParentAccount);
    updateAgeMessage(isParentAccount);

    // مراقبة تغيير نوع الحساب
    typeInputs.forEach(radio => {
        radio.addEventListener('change', function() {
            const isParent = this.value === 'parent';
            updateMaxDate(isParent);
            updateAgeMessage(isParent);
            
            // إعادة التحقق من التاريخ الحالي
            if (birthdateInput.value && !validateAge(birthdateInput.value, isParent)) {
                birthdateInput.value = '';
            }
        });
    });

    // مراقبة تغيير تاريخ الميلاد
    birthdateInput.addEventListener('change', function() {
        const isParent = document.getElementById('type_parent')?.checked ?? false;
        if (!validateAge(this.value, isParent)) {
            this.value = '';
        }
    });

    // مراقبة تغيير الصورة
    if (profileImage) {
        profileImage.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                if (validateImage(this.files[0])) {
                    previewImage(this);
                } else {
                    this.value = '';
                }
            }
        });
    }

    // التحقق من صحة النموذج قبل الإرسال
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const isParent = document.getElementById('type_parent')?.checked ?? false;
        
        // التحقق من تاريخ الميلاد
        if (!birthdateInput.value) {
            alert('يرجى إدخال تاريخ الميلاد');
            return;
        }

        if (!validateAge(birthdateInput.value, isParent)) {
            birthdateInput.value = '';
            return;
        }

        // التحقق من تطابق كلمات المرور
        const password = document.querySelector('input[name="password"]').value;
        const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
        
        if (password !== confirmPassword) {
            alert('كلمة المرور غير متطابقة');
            return;
        }

        // التحقق من الصورة إذا تم اختيارها
        if (profileImage && profileImage.files[0]) {
            if (!validateImage(profileImage.files[0])) {
                return;
            }
        }

        // إذا كل شيء صحيح، أرسل النموذج
        this.submit();
    });

    // تفعيل tooltips إذا كنت تستخدم Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // تفعيل التحقق من Bootstrap
    Array.from(document.querySelectorAll('.needs-validation')).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});

// دالة إظهار/إخفاء كلمة المرور (خارج DOMContentLoaded لأنها تُستدعى من HTML)
function togglePassword(button) {
    const input = button.parentElement.querySelector('input');
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>



<?php require_once '../includes/footer.php'; ?>
